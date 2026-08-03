<?php

namespace App\Tests\Unit\Recommendation;

use App\Entity\Product;
use App\Repository\ColdStartRecommendationRepository;
use App\Repository\GuestEventRepository;
use App\Repository\InteractionRepository;
use App\Repository\ProductRepository;
use App\Service\Recommendation\ColdStartRecommendationService;
use App\Service\Recommendation\SeasonalBoostService;
use PHPUnit\Framework\TestCase;

class ColdStartRecommendationServiceTest extends TestCase
{
    private GuestEventRepository $guestEventRepository;
    private InteractionRepository $interactionRepository;
    private ProductRepository $productRepository;
    private ColdStartRecommendationRepository $coldStartRecommendationRepository;
    private SeasonalBoostService $seasonalBoost;
    private ColdStartRecommendationService $service;

    protected function setUp(): void
    {
        $this->guestEventRepository = $this->createMock(GuestEventRepository::class);
        $this->interactionRepository = $this->createMock(InteractionRepository::class);
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->coldStartRecommendationRepository = $this->createMock(ColdStartRecommendationRepository::class);
        $this->seasonalBoost = $this->createMock(SeasonalBoostService::class);

        // No stubs configured here on purpose: PHPUnit's generated doubles
        // already return type-appropriate empty defaults ([], false, etc.)
        // for unconfigured calls, and stubbing the same method twice (once
        // here, once in a test) would make the first-registered stub win,
        // silently ignoring the test's override.

        $this->service = new ColdStartRecommendationService(
            $this->guestEventRepository,
            $this->interactionRepository,
            $this->productRepository,
            $this->coldStartRecommendationRepository,
            $this->seasonalBoost,
        );
    }

    private function makeProduct(int $id): Product
    {
        $product = new Product();
        $ref = new \ReflectionProperty(Product::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($product, $id);

        return $product;
    }

    /** @return array<int, array{productId: int, score: float}> */
    private function captureReplacedRows(): array
    {
        $captured = null;
        $this->coldStartRecommendationRepository->method('replaceAll')
            ->willReturnCallback(function (array $rows) use (&$captured) {
                $captured = $rows;
            });

        $this->service->rebuild();

        return $captured;
    }

    public function testBlendsFirstTouchPopularityAndTrendingCounts(): void
    {
        // First touch: product 1 dominant (3 sessions), product 2 minor (1).
        $this->guestEventRepository->method('findFirstProductIdPerSession')->willReturn([1, 1, 2]);
        $this->interactionRepository->method('findFirstProductIdPerUser')->willReturn([1]);

        // Trending: guest + interaction counts summed for product 2 and 3.
        $this->guestEventRepository->method('countRecentByProduct')->willReturn([2 => 4, 3 => 2]);
        $this->interactionRepository->method('countRecentByProduct')->willReturn([2 => 1, 3 => 1]);
        $this->productRepository->method('findBy')->willReturn([]);

        $rows = $this->captureReplacedRows();

        // normFirstTouch: 1 => 1.0, 2 => 1/3
        // normTrending:   2 => 1.0, 3 => 0.6
        // combined: 1 => 0.5*1.0 = 0.5
        //           2 => 0.5*(1/3) + 0.5*1.0 = 0.666667
        //           3 => 0.5*0.6 = 0.3
        $byId = [];
        foreach ($rows as $row) {
            $byId[$row['productId']] = $row['score'];
        }

        $this->assertCount(3, $rows);
        $this->assertEqualsWithDelta(0.5, $byId[1], 0.001);
        $this->assertEqualsWithDelta(0.6667, $byId[2], 0.001);
        $this->assertEqualsWithDelta(0.3, $byId[3], 0.001);

        // Highest combined score (product 2) must be ranked first.
        $this->assertSame(2, $rows[0]['productId']);
    }

    public function testAppliesSeasonalBoostToInSeasonProducts(): void
    {
        $this->guestEventRepository->method('findFirstProductIdPerSession')->willReturn([1]);

        $product1 = $this->makeProduct(1);
        $this->productRepository->method('findBy')->willReturn([$product1]);
        $this->seasonalBoost->method('isInSeason')->willReturnCallback(
            fn (Product $p) => 1 === $p->getId()
        );

        $rows = $this->captureReplacedRows();

        $byId = [];
        foreach ($rows as $row) {
            $byId[$row['productId']] = $row['score'];
        }

        // normFirstTouch[1] = 1.0 => combined 0.5*1.0 = 0.5, then +0.3 seasonal boost.
        $this->assertEqualsWithDelta(0.8, $byId[1], 0.001);
    }

    public function testFallsBackToNewestWhenThereIsNoActivityAtAll(): void
    {
        $products = [$this->makeProduct(10), $this->makeProduct(20), $this->makeProduct(30), $this->makeProduct(40)];
        $this->productRepository->method('findNewestRanked')->willReturn($products);
        $this->productRepository->method('findBy')->willReturn([]);

        $rows = $this->captureReplacedRows();

        $byId = [];
        foreach ($rows as $row) {
            $byId[$row['productId']] = $row['score'];
        }

        // rank 0..3 over 4 products: score = 1 - rank/4
        $this->assertEqualsWithDelta(1.0, $byId[10], 0.001);
        $this->assertEqualsWithDelta(0.75, $byId[20], 0.001);
        $this->assertEqualsWithDelta(0.5, $byId[30], 0.001);
        $this->assertEqualsWithDelta(0.25, $byId[40], 0.001);
        $this->assertSame(10, $rows[0]['productId']);
    }

    public function testReturnsZeroRowsWhenGenuinelyNothingExistsAnywhere(): void
    {
        // No activity and an empty catalog (findNewestRanked also empty).
        $result = $this->service->rebuild();

        $this->assertSame(['rows' => 0], $result);
    }

    public function testRespectsTopKLimitOfSixteen(): void
    {
        // 20 products, id N appears N times in first-touch => scores strictly
        // increasing with id, so ranking is deterministic.
        $firstTouch = [];
        for ($id = 1; $id <= 20; ++$id) {
            $firstTouch = array_merge($firstTouch, array_fill(0, $id, $id));
        }
        $this->guestEventRepository->method('findFirstProductIdPerSession')->willReturn($firstTouch);
        $this->productRepository->method('findBy')->willReturn([]);

        $rows = $this->captureReplacedRows();

        $this->assertCount(16, $rows);
        $ids = array_column($rows, 'productId');
        // Top 16 by score are ids 20 down to 5; ids 1-4 must be dropped.
        $this->assertContains(20, $ids);
        $this->assertContains(5, $ids);
        $this->assertNotContains(4, $ids);
        $this->assertNotContains(1, $ids);
    }

    public function testReturnValueReportsRowCount(): void
    {
        $this->guestEventRepository->method('findFirstProductIdPerSession')->willReturn([1, 2, 3]);
        $this->productRepository->method('findBy')->willReturn([]);

        $result = $this->service->rebuild();

        $this->assertSame(['rows' => 3], $result);
    }
}
