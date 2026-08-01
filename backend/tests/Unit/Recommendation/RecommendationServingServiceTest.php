<?php

namespace App\Tests\Unit\Recommendation;

use App\Entity\Interaction;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\ColdStartRecommendationRepository;
use App\Repository\ProductRelationRepository;
use App\Repository\UserRecommendationRepository;
use App\Service\Recommendation\RecommendationServingService;
use App\Repository\GuestEventRepository;
use App\Repository\InteractionRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class RecommendationServingServiceTest extends TestCase
{
    private ProductRelationRepository $productRelationRepository;
    private UserRecommendationRepository $userRecommendationRepository;
    private ColdStartRecommendationRepository $coldStartRecommendationRepository;
    private ProductRepository $productRepository;
    private InteractionRepository $interactionRepository;
    private GuestEventRepository $guestEventRepository;
    private OrderRepository $orderRepository;
    private RecommendationServingService $service;

    protected function setUp(): void
    {
        $this->productRelationRepository = $this->createMock(ProductRelationRepository::class);
        $this->userRecommendationRepository = $this->createMock(UserRecommendationRepository::class);
        $this->coldStartRecommendationRepository = $this->createMock(ColdStartRecommendationRepository::class);
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->interactionRepository = $this->createMock(InteractionRepository::class);
        $this->guestEventRepository = $this->createMock(GuestEventRepository::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);

        $this->service = new RecommendationServingService(
            $this->productRelationRepository,
            $this->userRecommendationRepository,
            $this->coldStartRecommendationRepository,
            $this->productRepository,
            $this->interactionRepository,
            $this->guestEventRepository,
            $this->orderRepository,
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

    public function testForUserUsesPrecomputedListAndExcludesPurchasedProducts(): void
    {
        $this->userRecommendationRepository->method('findForUser')->willReturn([
            ['productId' => 1, 'score' => 5.0],
            ['productId' => 2, 'score' => 3.0],
        ]);
        $this->orderRepository->method('findPurchasedProductIds')->willReturn([2]);
        $this->productRepository->method('findBy')->willReturn([$this->makeProduct(1)]);

        $result = $this->service->forUser(new User(), 8);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]->getId());
    }

    public function testForUserFallsBackToLiveLookupFromInteractionHistory(): void
    {
        $this->userRecommendationRepository->method('findForUser')->willReturn([]);

        $product = $this->makeProduct(5);
        $interaction = new Interaction();
        $interaction->setType('view');
        $interaction->setProduct($product);
        $this->interactionRepository->method('findByUser')->willReturn([$interaction]);

        $this->productRelationRepository->method('findRelationsForProducts')->willReturn([]);
        $this->productRepository->method('findBy')->willReturn([$product]);

        $result = $this->service->forUser(new User(), 8);

        $this->assertCount(1, $result);
        $this->assertSame(5, $result[0]->getId());
    }

    public function testForUserFallsBackToColdStartWhenNoHistoryAtAll(): void
    {
        $this->userRecommendationRepository->method('findForUser')->willReturn([]);
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

    public function testResolveAndFilterStockExcludesOutOfStockProducts(): void
    {
        $this->userRecommendationRepository->method('findForUser')->willReturn([
            ['productId' => 1, 'score' => 5.0],
            ['productId' => 2, 'score' => 3.0],
        ]);
        $this->orderRepository->method('findPurchasedProductIds')->willReturn([]);
        $this->productRepository->method('findBy')->willReturn([
            $this->makeProduct(1, 0),
            $this->makeProduct(2, 5),
        ]);

        $result = $this->service->forUser(new User(), 8);

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]->getId());
    }

    public function testResolveAndFilterStockRespectsLimit(): void
    {
        $this->userRecommendationRepository->method('findForUser')->willReturn([
            ['productId' => 1, 'score' => 5.0],
            ['productId' => 2, 'score' => 4.0],
            ['productId' => 3, 'score' => 3.0],
        ]);
        $this->orderRepository->method('findPurchasedProductIds')->willReturn([]);
        $this->productRepository->method('findBy')->willReturn([
            $this->makeProduct(1),
            $this->makeProduct(2),
            $this->makeProduct(3),
        ]);

        $result = $this->service->forUser(new User(), 2);

        $this->assertCount(2, $result);
    }

    public function testForProductReturnsSimilarAndComplementaryLists(): void
    {
        $this->productRelationRepository->method('findTopForProduct')
            ->willReturnMap([
                [1, 'similar', 16, [2]],
                [1, 'complementary', 16, [3]],
            ]);
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
