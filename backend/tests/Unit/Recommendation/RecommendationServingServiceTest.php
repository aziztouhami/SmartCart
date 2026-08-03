<?php

namespace App\Tests\Unit\Recommendation;

use App\Entity\Interaction;
use App\Entity\Product;
use App\Entity\ProductRelation;
use App\Entity\User;
use App\Repository\ColdStartRecommendationRepository;
use App\Repository\GuestEventRepository;
use App\Repository\InteractionRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRelationRepository;
use App\Repository\ProductRepository;
use App\Service\Recommendation\CachedCollaborativeFilteringModel;
use App\Service\Recommendation\ContentRecommendationService;
use App\Service\Recommendation\HybridRecommendationScorer;
use App\Service\Recommendation\RecommendationBusinessRules;
use App\Service\Recommendation\RecommendationServingService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class RecommendationServingServiceTest extends TestCase
{
    private ProductRelationRepository $productRelationRepository;
    private ColdStartRecommendationRepository $coldStartRecommendationRepository;
    private ProductRepository $productRepository;
    private InteractionRepository $interactionRepository;
    private GuestEventRepository $guestEventRepository;
    private OrderRepository $orderRepository;
    private ContentRecommendationService $contentRecommendation;
    private CachedCollaborativeFilteringModel $cachedCfModel;
    private HybridRecommendationScorer $hybridScorer;
    private RecommendationBusinessRules $businessRules;
    private RecommendationServingService $service;

    protected function setUp(): void
    {
        $this->productRelationRepository = $this->createMock(ProductRelationRepository::class);
        $this->coldStartRecommendationRepository = $this->createMock(ColdStartRecommendationRepository::class);
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->interactionRepository = $this->createMock(InteractionRepository::class);
        $this->guestEventRepository = $this->createMock(GuestEventRepository::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->contentRecommendation = $this->createMock(ContentRecommendationService::class);
        $this->cachedCfModel = $this->createMock(CachedCollaborativeFilteringModel::class);
        $this->hybridScorer = $this->createMock(HybridRecommendationScorer::class);
        $this->businessRules = $this->createMock(RecommendationBusinessRules::class);

        $this->productRepository->method('findNewestRanked')->willReturn([]);
        $this->cachedCfModel->method('get')->willReturn([]);

        $this->service = new RecommendationServingService(
            $this->productRelationRepository,
            $this->coldStartRecommendationRepository,
            $this->productRepository,
            $this->interactionRepository,
            $this->guestEventRepository,
            $this->orderRepository,
            $this->contentRecommendation,
            $this->cachedCfModel,
            $this->hybridScorer,
            $this->businessRules,
        );
    }

    private function makeProduct(int $id, int $stock = 5): Product
    {
        $product = new Product();
        $ref = new \ReflectionProperty(Product::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($product, $id);
        $product->setStock($stock);

        return $product;
    }

    public function testForUserUsesHybridScoringFromInteractionHistory(): void
    {
        $seed = $this->makeProduct(5);
        $related = $this->makeProduct(6);

        $interaction = new Interaction();
        $interaction->setType('view');
        $interaction->setProduct($seed);
        $this->interactionRepository->method('findByUser')->willReturn([$interaction]);

        $this->productRepository->method('findAll')->willReturn([$seed, $related]);
        $this->cachedCfModel->expects($this->once())->method('get');
        $this->hybridScorer->method('score')->willReturn([6 => 2.0, 5 => 0.5]);
        $this->businessRules->method('apply')->willReturnArgument(0);
        $this->productRepository->method('findBy')->willReturn([$seed, $related]);

        $result = $this->service->forUser(new User(), 8);

        $this->assertCount(2, $result);
        $this->assertSame(6, $result[0]->getId());
    }

    public function testForUserExcludesPurchasedProducts(): void
    {
        $seed = $this->makeProduct(5);

        $interaction = new Interaction();
        $interaction->setType('purchase');
        $interaction->setProduct($seed);
        $this->interactionRepository->method('findByUser')->willReturn([$interaction]);

        $this->productRepository->method('findAll')->willReturn([$seed]);
        $this->hybridScorer->method('score')->willReturn([5 => 3.0]);
        $this->orderRepository->method('findPurchasedProductIds')->willReturn([5]);

        // The purchased id must already be gone from what reaches business
        // rules, not just filtered afterward.
        $this->businessRules->expects($this->once())->method('apply')
            ->with([], $this->anything())
            ->willReturn([]);

        $result = $this->service->forUser(new User(), 8);

        $this->assertSame([], $result);
    }

    public function testForUserAppliesBusinessRulesToHybridScores(): void
    {
        $seed = $this->makeProduct(5);
        $candidate = $this->makeProduct(6);

        $interaction = new Interaction();
        $interaction->setType('view');
        $interaction->setProduct($seed);
        $this->interactionRepository->method('findByUser')->willReturn([$interaction]);

        $this->productRepository->method('findAll')->willReturn([$seed, $candidate]);
        $this->hybridScorer->method('score')->willReturn([6 => 1.0]);
        // Business rules is what determines the final ranking here, not the
        // raw hybrid score — if it were bypassed, this candidate would be
        // dropped instead of kept.
        $this->businessRules->method('apply')->willReturn([6 => 5.0]);
        $this->productRepository->method('findBy')->willReturn([$candidate]);

        $result = $this->service->forUser(new User(), 8);

        $this->assertCount(1, $result);
        $this->assertSame(6, $result[0]->getId());
    }

    public function testForUserFallsBackToColdStartWhenNoHistoryAtAll(): void
    {
        $this->interactionRepository->method('findByUser')->willReturn([]);

        $coldProduct = $this->makeProduct(9);
        $this->coldStartRecommendationRepository->method('findTopProductIds')->willReturn([9]);
        $this->productRepository->method('findBy')->willReturn([$coldProduct]);

        $result = $this->service->forUser(new User(), 8);

        $this->assertCount(1, $result);
        $this->assertSame(9, $result[0]->getId());
    }

    public function testForGuestUsesColdStartWhenNoSessionHeader(): void
    {
        $coldProduct = $this->makeProduct(11);
        $this->coldStartRecommendationRepository->method('findTopProductIds')->willReturn([11]);
        $this->productRepository->method('findBy')->willReturn([$coldProduct]);

        $request = new Request();

        $result = $this->service->forGuest($request, 8);

        $this->assertCount(1, $result);
        $this->assertSame(11, $result[0]->getId());
    }

    public function testForGuestUsesLiveLookupWhenSessionHasRecentProducts(): void
    {
        $this->guestEventRepository->method('findRecentProductIdsBySession')->willReturn([7]);
        $this->productRelationRepository->method('findRelationsForProducts')->willReturn([
            ['productId' => 7, 'relatedProductId' => 8, 'score' => 4.0],
        ]);
        $this->productRepository->method('findBy')->willReturn([$this->makeProduct(7), $this->makeProduct(8)]);

        $request = new Request(server: ['HTTP_X_SESSION_ID' => 'session-123']);

        $result = $this->service->forGuest($request, 8);

        $resultIds = array_map(fn ($p) => $p->getId(), $result);
        $this->assertContains(7, $resultIds);
        $this->assertContains(8, $resultIds);
    }

    public function testColdStartExcludesOutOfStockProducts(): void
    {
        $this->interactionRepository->method('findByUser')->willReturn([]);
        $this->coldStartRecommendationRepository->method('findTopProductIds')->willReturn([1, 2]);
        $this->productRepository->method('findBy')->willReturn([
            $this->makeProduct(1, 0),
            $this->makeProduct(2, 5),
        ]);

        $result = $this->service->forUser(new User(), 8);

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]->getId());
    }

    public function testColdStartRespectsLimit(): void
    {
        $this->interactionRepository->method('findByUser')->willReturn([]);
        $this->coldStartRecommendationRepository->method('findTopProductIds')->willReturn([1, 2, 3]);
        $this->productRepository->method('findBy')->willReturn([
            $this->makeProduct(1),
            $this->makeProduct(2),
            $this->makeProduct(3),
        ]);

        $result = $this->service->forUser(new User(), 2);

        $this->assertCount(2, $result);
    }

    public function testForProductReturnsComplementaryFromBatchAndSimilarFromLiveContentScoring(): void
    {
        $current = $this->makeProduct(1);
        $similarCandidate = $this->makeProduct(2);
        $complementary = $this->makeProduct(3);

        $this->productRelationRepository->method('findTopForProduct')
            ->with(1, ProductRelation::TYPE_COMPLEMENTARY, 16, $this->anything())
            ->willReturn([3]);

        $this->productRepository->method('findAll')->willReturn([$current, $similarCandidate]);
        $this->contentRecommendation->method('predictForUser')->willReturn([2 => 1.0]);

        $this->productRepository->method('findBy')->willReturnCallback(
            fn ($criteria) => array_map(fn ($id) => $this->makeProduct($id), $criteria['id'])
        );

        $result = $this->service->forProduct(1, 8);

        $this->assertCount(1, $result['similar']);
        $this->assertSame(2, $result['similar'][0]->getId());
        $this->assertCount(1, $result['complementary']);
        $this->assertSame(3, $result['complementary'][0]->getId());
    }
}
