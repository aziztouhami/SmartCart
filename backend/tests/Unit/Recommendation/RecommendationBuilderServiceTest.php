<?php

namespace App\Tests\Unit\Recommendation;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductRelation;
use App\Repository\GuestEventRepository;
use App\Repository\InteractionRepository;
use App\Repository\ProductRelationRepository;
use App\Repository\ProductRepository;
use App\Service\Recommendation\ContentSimilarityService;
use App\Service\Recommendation\RecommendationBuilderService;
use PHPUnit\Framework\TestCase;

class RecommendationBuilderServiceTest extends TestCase
{
    private GuestEventRepository $guestEventRepository;
    private InteractionRepository $interactionRepository;
    private ProductRepository $productRepository;
    private ProductRelationRepository $productRelationRepository;
    private ContentSimilarityService $contentSimilarity;
    private RecommendationBuilderService $service;

    protected function setUp(): void
    {
        $this->guestEventRepository = $this->createMock(GuestEventRepository::class);
        $this->interactionRepository = $this->createMock(InteractionRepository::class);
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->productRelationRepository = $this->createMock(ProductRelationRepository::class);
        $this->contentSimilarity = $this->createMock(ContentSimilarityService::class);

        // Neutral defaults: no behavioral groups, no content candidates.
        $this->guestEventRepository->method('groupedSessionsSince')->willReturn([]);
        $this->interactionRepository->method('groupedByUserSince')->willReturn([]);
        $this->contentSimilarity->method('bucketByShared')->willReturn([
            'category' => [], 'parentCategory' => [], 'brand' => [], 'type' => [],
        ]);
        $this->contentSimilarity->method('isTrained')->willReturn(false);
        $this->contentSimilarity->method('getConfidence')->willReturn(0.0);
        $this->contentSimilarity->method('getWeights')->willReturn([
            'category' => 3.0, 'parentCategory' => 1.0, 'brand' => 1.5, 'type' => 2.0, 'featureMatch' => 4.0,
        ]);

        $this->service = new RecommendationBuilderService(
            $this->guestEventRepository,
            $this->interactionRepository,
            $this->productRepository,
            $this->productRelationRepository,
            $this->contentSimilarity,
        );
    }

    private function makeProduct(int $id, ?Category $category = null, ?\DateTimeImmutable $createdAt = null): Product
    {
        $product = new Product();
        $ref = new \ReflectionProperty(Product::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($product, $id);
        $product->setCreatedAt($createdAt ?? new \DateTimeImmutable('-1 day'));
        if ($category) {
            $product->setCategory($category);
        }

        return $product;
    }

    private function makeCategory(int $id): Category
    {
        $category = new Category();
        $ref = new \ReflectionProperty(Category::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($category, $id);

        return $category;
    }

    /** @return array<int, array{productId: int, relatedProductId: int, score: float, type: string}> */
    private function captureReplacedRows(): array
    {
        $captured = null;
        $this->productRelationRepository->method('replaceAll')
            ->willReturnCallback(function (array $rows) use (&$captured) {
                $captured = $rows;
            });

        $this->service->rebuild();

        return $captured;
    }

    private function findRow(array $rows, int $productId, int $relatedProductId, string $type): ?array
    {
        foreach ($rows as $row) {
            if ($row['productId'] === $productId && $row['relatedProductId'] === $relatedProductId && $row['type'] === $type) {
                return $row;
            }
        }

        return null;
    }

    public function testHandlesEmptyCatalogWithoutError(): void
    {
        $this->productRepository->method('findAll')->willReturn([]);

        $result = $this->service->rebuild();

        $this->assertSame(0, $result['groups']);
        $this->assertSame(0, $result['products']);
        $this->assertSame(0, $result['pairs']);
    }

    public function testWeighsBehavioralPairsByCombinedTierStrength(): void
    {
        // Five isolated pairs, each in its own category so none are excluded
        // as "same category", and each pair already has a relation so the
        // trending-fallback path never kicks in for these products.
        $categories = [];
        $products = [];
        for ($i = 1; $i <= 10; ++$i) {
            $categories[$i] = $this->makeCategory(1000 + $i);
            $products[$i] = $this->makeProduct($i, $categories[$i]);
        }
        $this->productRepository->method('findAll')->willReturn(array_values($products));

        // (1,2) view+view, (3,4) cart+cart, (5,6) view+purchase, (7,8) cart+purchase, (9,10) purchase+purchase
        //
        // A fresh mock, not a second ->method() call on $this->guestEventRepository:
        // setUp() already stubbed groupedSessionsSince() to return [] on that same
        // mock/method pair, and a second stub there is not reliably honored.
        $this->guestEventRepository = $this->createMock(GuestEventRepository::class);
        $this->guestEventRepository->method('groupedSessionsSince')->willReturn([
            [1 => 'view', 2 => 'view'],
            [3 => 'cart', 4 => 'cart'],
            [5 => 'view', 6 => 'purchase'],
            [7 => 'cart', 8 => 'purchase'],
            [9 => 'purchase', 10 => 'purchase'],
        ]);
        $this->service = new RecommendationBuilderService(
            $this->guestEventRepository,
            $this->interactionRepository,
            $this->productRepository,
            $this->productRelationRepository,
            $this->contentSimilarity,
        );

        $rows = $this->captureReplacedRows();

        $expectations = [
            [1, 2, 1.0],   // 1-1
            [3, 4, 4.0],   // 2-2
            [5, 6, 3.5],   // 1-3
            [7, 8, 5.0],   // 2-3
            [9, 10, 6.0],  // 3-3
        ];
        foreach ($expectations as [$a, $b, $expectedScore]) {
            $row = $this->findRow($rows, $a, $b, ProductRelation::TYPE_COMPLEMENTARY);
            $this->assertNotNull($row, "expected a complementary row for ({$a},{$b})");
            $this->assertSame($expectedScore, $row['score']);

            // Behavioral relations are mirrored in both directions.
            $mirrored = $this->findRow($rows, $b, $a, ProductRelation::TYPE_COMPLEMENTARY);
            $this->assertNotNull($mirrored);
            $this->assertSame($expectedScore, $mirrored['score']);
        }
    }

    public function testIgnoresSingleProductGroupsWhenCountingGroups(): void
    {
        $products = [$this->makeProduct(1), $this->makeProduct(2), $this->makeProduct(3)];
        $this->productRepository->method('findAll')->willReturn($products);

        // Fresh mock — see note in testWeighsBehavioralPairsByCombinedTierStrength.
        $this->guestEventRepository = $this->createMock(GuestEventRepository::class);
        $this->guestEventRepository->method('groupedSessionsSince')->willReturn([
            [1 => 'view', 2 => 'view'], // valid pair
            [3 => 'view'],              // single product => discarded entirely
        ]);
        $this->service = new RecommendationBuilderService(
            $this->guestEventRepository,
            $this->interactionRepository,
            $this->productRepository,
            $this->productRelationRepository,
            $this->contentSimilarity,
        );

        $result = $this->service->rebuild();

        $this->assertSame(1, $result['groups']);
    }

    public function testExcludesSameCategoryPairsFromComplementaryRelations(): void
    {
        $category = $this->makeCategory(500);
        $p1 = $this->makeProduct(1, $category);
        $p2 = $this->makeProduct(2, $category); // same category as p1
        // Other-category filler so the trending fallback (when 1/2 lose their
        // only behavioral relation to the same-category exclusion) has real
        // alternatives to suggest instead of re-linking 1 and 2 to each other
        // anyway for lack of anything else in the whole catalog.
        $p3 = $this->makeProduct(3, $this->makeCategory(501));
        $p4 = $this->makeProduct(4, $this->makeCategory(502));
        $this->productRepository->method('findAll')->willReturn([$p1, $p2, $p3, $p4]);

        // Fresh mock — see note in testWeighsBehavioralPairsByCombinedTierStrength.
        $this->guestEventRepository = $this->createMock(GuestEventRepository::class);
        $this->guestEventRepository->method('groupedSessionsSince')->willReturn([
            [1 => 'purchase', 2 => 'purchase'],
        ]);
        $this->service = new RecommendationBuilderService(
            $this->guestEventRepository,
            $this->interactionRepository,
            $this->productRepository,
            $this->productRelationRepository,
            $this->contentSimilarity,
        );

        $rows = $this->captureReplacedRows();

        // Behavioral co-occurred (purchase+purchase would score 6.0 — see
        // PAIR_WEIGHTS['3-3']), but same category => excluded from
        // "complementary" scoring. A row MAY still exist for (1,2) via the
        // low-confidence trending fallback (nothing to do with category
        // exclusion — it's what happens when a product has no real relations
        // left at all), so the real assertion is "never the actual
        // behavioral score", not "no row whatsoever".
        $row = $this->findRow($rows, 1, 2, ProductRelation::TYPE_COMPLEMENTARY);
        if (null !== $row) {
            $this->assertNotSame(6.0, $row['score']);
        }
        $mirrored = $this->findRow($rows, 2, 1, ProductRelation::TYPE_COMPLEMENTARY);
        if (null !== $mirrored) {
            $this->assertNotSame(6.0, $mirrored['score']);
        }
    }

    public function testKeepsSameCategoryPairsAvailableForContentCandidates(): void
    {
        $category = $this->makeCategory(500);
        $p1 = $this->makeProduct(1, $category);
        $p2 = $this->makeProduct(2, $category);
        $this->productRepository->method('findAll')->willReturn([$p1, $p2]);

        // Fresh mock — setUp() already stubbed bucketByShared() to return
        // all-empty buckets on this same mock/method pair, and a second stub
        // there is not reliably honored.
        $this->contentSimilarity = $this->createMock(ContentSimilarityService::class);
        $this->contentSimilarity->method('bucketByShared')->willReturn([
            'category' => [500 => [$p1, $p2]], 'parentCategory' => [], 'brand' => [], 'type' => [],
        ]);
        $this->contentSimilarity->method('score')->with($p1, $p2)->willReturn(7.5);
        $this->contentSimilarity->method('isTrained')->willReturn(false);
        $this->contentSimilarity->method('getConfidence')->willReturn(0.0);
        $this->contentSimilarity->method('getWeights')->willReturn([]);
        $this->service = new RecommendationBuilderService(
            $this->guestEventRepository,
            $this->interactionRepository,
            $this->productRepository,
            $this->productRelationRepository,
            $this->contentSimilarity,
        );

        $rows = $this->captureReplacedRows();

        $row = $this->findRow($rows, 1, 2, ProductRelation::TYPE_SIMILAR);
        $this->assertNotNull($row);
        $this->assertSame(7.5, $row['score']);
    }

    public function testFallsBackToTrendingWhenAProductHasNoRelationsAtAll(): void
    {
        $newest = $this->makeProduct(1, createdAt: new \DateTimeImmutable('-1 day'));
        $middle = $this->makeProduct(2, createdAt: new \DateTimeImmutable('-2 days'));
        // Product 3 has zero content and zero behavioral signal.
        $isolated = $this->makeProduct(3, createdAt: new \DateTimeImmutable('-3 days'));
        $this->productRepository->method('findAll')->willReturn([$newest, $middle, $isolated]);

        // Give products 1 and 2 a real relation so only product 3 needs the fallback.
        $this->contentSimilarity->method('bucketByShared')->willReturn([
            'category' => [], 'parentCategory' => [], 'brand' => [], 'type' => [1 => [$newest, $middle]],
        ]);
        $this->contentSimilarity->method('score')->willReturn(2.0);

        $rows = $this->captureReplacedRows();

        $fallbackRow = $this->findRow($rows, 3, 1, ProductRelation::TYPE_SIMILAR);
        $this->assertNotNull($fallbackRow);
        $this->assertSame(0.5, $fallbackRow['score']); // low-confidence fallback weight

        $fallbackRow2 = $this->findRow($rows, 3, 2, ProductRelation::TYPE_SIMILAR);
        $this->assertNotNull($fallbackRow2);
        $this->assertSame(0.5, $fallbackRow2['score']);
    }

    public function testCapsRelationsAtTopTwelvePerProduct(): void
    {
        $hub = $this->makeProduct(100);
        $others = [];
        for ($id = 1; $id <= 15; ++$id) {
            $others[] = $this->makeProduct($id);
        }
        $products = array_merge([$hub], $others);
        $this->productRepository->method('findAll')->willReturn($products);

        // Fresh mock — see note in testKeepsSameCategoryPairsAvailableForContentCandidates.
        $this->contentSimilarity = $this->createMock(ContentSimilarityService::class);
        $this->contentSimilarity->method('bucketByShared')->willReturn([
            'category' => [1 => $products], 'parentCategory' => [], 'brand' => [], 'type' => [],
        ]);
        // Score every pair involving the hub as the other product's id (1..15,
        // strictly increasing so the top-12 cut is unambiguous); every other
        // pair gets a negligible constant score that never competes with the hub's own.
        $this->contentSimilarity->method('score')->willReturnCallback(
            function (Product $a, Product $b) use ($hub) {
                if ($a->getId() === $hub->getId()) {
                    return (float) $b->getId();
                }
                if ($b->getId() === $hub->getId()) {
                    return (float) $a->getId();
                }

                return 0.01;
            }
        );
        $this->contentSimilarity->method('isTrained')->willReturn(false);
        $this->contentSimilarity->method('getConfidence')->willReturn(0.0);
        $this->contentSimilarity->method('getWeights')->willReturn([]);
        $this->service = new RecommendationBuilderService(
            $this->guestEventRepository,
            $this->interactionRepository,
            $this->productRepository,
            $this->productRelationRepository,
            $this->contentSimilarity,
        );

        $rows = $this->captureReplacedRows();

        $hubRows = array_filter($rows, fn ($r) => 100 === $r['productId'] && ProductRelation::TYPE_SIMILAR === $r['type']);
        $this->assertCount(12, $hubRows);

        $relatedIds = array_map(fn ($r) => $r['relatedProductId'], $hubRows);
        // Top 12 of 1..15 are 4..15; ids 1,2,3 must be dropped.
        foreach ([4, 5, 15] as $expectedKept) {
            $this->assertContains($expectedKept, $relatedIds);
        }
        foreach ([1, 2, 3] as $expectedDropped) {
            $this->assertNotContains($expectedDropped, $relatedIds);
        }
    }

    public function testPrunesOldGuestEventsAfterRebuilding(): void
    {
        $this->productRepository->method('findAll')->willReturn([]);

        $this->guestEventRepository->expects($this->once())->method('pruneOlderThan');

        $this->service->rebuild();
    }

    public function testReturnValueReportsContentWeightMetadataFromContentSimilarityService(): void
    {
        $this->productRepository->method('findAll')->willReturn([]);
        $this->contentSimilarity = $this->createMock(ContentSimilarityService::class);
        $this->contentSimilarity->method('bucketByShared')->willReturn([
            'category' => [], 'parentCategory' => [], 'brand' => [], 'type' => [],
        ]);
        $this->contentSimilarity->method('isTrained')->willReturn(true);
        $this->contentSimilarity->method('getConfidence')->willReturn(0.4567);
        $this->contentSimilarity->method('getWeights')->willReturn(['category' => 6.0]);
        $service = new RecommendationBuilderService(
            $this->guestEventRepository,
            $this->interactionRepository,
            $this->productRepository,
            $this->productRelationRepository,
            $this->contentSimilarity,
        );

        $result = $service->rebuild();

        $this->assertTrue($result['contentWeightsLearned']);
        $this->assertSame(0.457, $result['contentWeightsConfidence']); // rounded to 3 decimals
        $this->assertSame(['category' => 6.0], $result['contentWeights']);
    }

    public function testTrainsContentSimilarityBeforeScoringCandidates(): void
    {
        $products = [$this->makeProduct(1), $this->makeProduct(2)];
        $this->productRepository->method('findAll')->willReturn($products);

        $this->contentSimilarity->expects($this->once())->method('train');

        $this->service->rebuild();
    }
}
