<?php

namespace App\Tests\Unit\Recommendation;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\ColdStartRecommendationRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\PromotionRepository;
use App\Repository\UserRecommendationRepository;
use App\Repository\UserRepository;
use App\Service\Recommendation\CachedCollaborativeFilteringModel;
use App\Service\Recommendation\CollaborativeFilteringService;
use App\Service\Recommendation\HybridRecommendationScorer;
use App\Service\Recommendation\RecommendationBusinessRules;
use App\Service\Recommendation\UserRecommendationBuilderService;
use PHPUnit\Framework\TestCase;

class UserRecommendationBuilderServiceTest extends TestCase
{
    private UserRepository $userRepository;
    private ProductRepository $productRepository;
    private OrderRepository $orderRepository;
    private PromotionRepository $promotionRepository;
    private UserRecommendationRepository $userRecommendationRepository;
    private ColdStartRecommendationRepository $coldStartRecommendationRepository;
    private CollaborativeFilteringService $collaborativeFiltering;
    private CachedCollaborativeFilteringModel $cachedCfModel;
    private HybridRecommendationScorer $hybridScorer;
    private RecommendationBusinessRules $businessRules;
    private UserRecommendationBuilderService $service;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->orderRepository->method('findPurchasedProductIds')->willReturn([]);
        $this->promotionRepository = $this->createMock(PromotionRepository::class);
        $this->promotionRepository->method('findActiveForProducts')->willReturn([]);
        $this->userRecommendationRepository = $this->createMock(UserRecommendationRepository::class);
        $this->coldStartRecommendationRepository = $this->createMock(ColdStartRecommendationRepository::class);
        $this->coldStartRecommendationRepository->method('findTopWithScores')->willReturn([]);
        $this->collaborativeFiltering = $this->createMock(CollaborativeFilteringService::class);
        $this->cachedCfModel = $this->createMock(CachedCollaborativeFilteringModel::class);
        $this->hybridScorer = $this->createMock(HybridRecommendationScorer::class);
        $this->businessRules = $this->createMock(RecommendationBusinessRules::class);

        $this->service = new UserRecommendationBuilderService(
            $this->userRepository,
            $this->productRepository,
            $this->orderRepository,
            $this->promotionRepository,
            $this->userRecommendationRepository,
            $this->coldStartRecommendationRepository,
            $this->collaborativeFiltering,
            $this->cachedCfModel,
            $this->hybridScorer,
            $this->businessRules,
        );
    }

    private function makeUser(int $id): User
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($user, $id);

        return $user;
    }

    private function makeProduct(int $id): Product
    {
        $product = new Product();
        $ref = new \ReflectionProperty(Product::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($product, $id);
        $product->setStock(5);
        $product->setCreatedAt(new \DateTimeImmutable('-30 days'));
        $product->setCategory($this->makeCategory(1));

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

    public function testRebuildTrainsCfOnceAndScoresUsersWithHistoryViaHybridScorer(): void
    {
        $product = $this->makeProduct(10);
        $this->productRepository->method('findAll')->willReturn([$product]);

        $user = $this->makeUser(1);
        $this->userRepository->method('findAll')->willReturn([$user]);

        $tasteMatrix = [1 => [10 => 3.0]];
        $this->collaborativeFiltering->expects($this->once())->method('buildTasteMatrix')->willReturn($tasteMatrix);

        $trainedModel = ['trained'];
        $this->cachedCfModel->expects($this->once())->method('refresh')->with($tasteMatrix)->willReturn($trainedModel);

        $this->hybridScorer->expects($this->once())
            ->method('score')
            ->with($user, [10 => 3.0], $trainedModel, [10], [10 => $product])
            ->willReturn([10 => 2.5]);

        $this->businessRules->method('apply')->willReturn([10 => 2.5]);

        $this->userRecommendationRepository->expects($this->once())
            ->method('replaceAll')
            ->with([
                ['userId' => 1, 'productId' => 10, 'score' => 2.5, 'source' => 'hybrid'],
            ]);

        $stats = $this->service->rebuild();

        $this->assertSame(1, $stats['hybrid']);
        $this->assertSame(0, $stats['fallback']);
        $this->assertSame(1, $stats['rows']);
    }

    public function testRebuildFallsBackToTrendingForUsersWithNoRatingHistory(): void
    {
        $this->productRepository->method('findAll')->willReturn([]);

        $user = $this->makeUser(2);
        $this->userRepository->method('findAll')->willReturn([$user]);

        $this->collaborativeFiltering->method('buildTasteMatrix')->willReturn([]); // no ratings for anyone
        $this->cachedCfModel->method('refresh')->willReturn([]);
        $this->hybridScorer->expects($this->never())->method('score');

        $this->coldStartRecommendationRepository->method('findTopWithScores')->willReturn([99 => 1.0]);
        $this->businessRules->method('apply')->willReturn([]);

        $stats = $this->service->rebuild();

        $this->assertSame(0, $stats['hybrid']);
        $this->assertSame(1, $stats['fallback']);
    }

    public function testApplyBusinessRulesExcludesPurchasedProductsBeforeDelegating(): void
    {
        $product = $this->makeProduct(10);
        $this->productRepository->method('findAll')->willReturn([$product]);

        $user = $this->makeUser(1);
        $this->userRepository->method('findAll')->willReturn([$user]);

        $this->collaborativeFiltering->method('buildTasteMatrix')->willReturn([1 => [10 => 3.0]]);
        $this->cachedCfModel->method('refresh')->willReturn([]);
        $this->hybridScorer->method('score')->willReturn([10 => 2.5]);

        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->orderRepository->method('findPurchasedProductIds')->willReturn([10]);
        $service = new UserRecommendationBuilderService(
            $this->userRepository,
            $this->productRepository,
            $this->orderRepository,
            $this->promotionRepository,
            $this->userRecommendationRepository,
            $this->coldStartRecommendationRepository,
            $this->collaborativeFiltering,
            $this->cachedCfModel,
            $this->hybridScorer,
            $this->businessRules,
        );

        // Product 10 was purchased — it must already be gone from what
        // reaches business rules, not just filtered afterward.
        $this->businessRules->expects($this->once())->method('apply')
            ->with([], $this->anything(), $this->anything(), $this->anything())
            ->willReturn([]);

        $service->rebuild();
    }
}
