<?php

namespace App\Controller\Admin;

use App\DTO\Product\ProductListItem;
use App\Repository\InteractionRepository;
use App\Repository\ProductRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/stats', name: 'api_admin_stats_')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Admin — Stats', description: 'Behavior & recommendation analytics (ROLE_ADMIN required)')]
class AdminStatsController extends AbstractController
{
    public function __construct(
        private InteractionRepository $interactionRepository,
        private ProductRepository $productRepository,
    ) {}

    /**
     * Global breakdown of user interactions + top products per interaction type.
     */
    #[Route('/behaviors', name: 'behaviors', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin/stats/behaviors',
        operationId: 'adminBehaviorStats',
        summary: 'User behavior overview',
        security: [['Bearer' => []]],
        responses: [new OA\Response(response: 200, description: 'Behavior statistics')]
    )]
    public function behaviors(): JsonResponse
    {
        $overview    = $this->interactionRepository->getInteractionTypeBreakdown();
        $topViewed   = $this->interactionRepository->getTopProductsByType('view', 5);
        $topCarted   = $this->interactionRepository->getTopProductsByType('cart', 5);
        $topBought   = $this->interactionRepository->getTopProductsByType('purchase', 5);
        $topRated    = $this->interactionRepository->getTopProductsByType('rating', 5);

        return $this->json([
            'overview' => [
                'views'     => $overview['view'],
                'cartAdds'  => $overview['cart'],
                'purchases' => $overview['purchase'],
                'ratings'   => $overview['rating'],
            ],
            'topViewed'      => array_map(fn($p) => ProductListItem::fromEntity($p), $topViewed),
            'topAddedToCart' => array_map(fn($p) => ProductListItem::fromEntity($p), $topCarted),
            'topPurchased'   => array_map(fn($p) => ProductListItem::fromEntity($p), $topBought),
            'topRated'       => array_map(fn($p) => ProductListItem::fromEntity($p), $topRated),
        ]);
    }

    /**
     * Per-product insights: interaction breakdown + collaborative-filtering recommendations.
     */
    #[Route('/product/{productId}/insights', name: 'product_insights', methods: ['GET'], requirements: ['productId' => '\d+'])]
    #[OA\Get(
        path: '/api/admin/stats/product/{productId}/insights',
        operationId: 'adminProductInsights',
        summary: 'Product behavior insights and co-interaction recommendations',
        security: [['Bearer' => []]],
        parameters: [new OA\Parameter(name: 'productId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Product insights'),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    public function productInsights(int $productId): JsonResponse
    {
        $product = $this->productRepository->find($productId);
        if (!$product) {
            return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        $interactionCounts  = $this->interactionRepository->getProductInteractionCounts($product);
        $boughtWith         = $this->interactionRepository->getCoInteractedProducts($product, 'purchase', 5);
        $viewedWith         = $this->interactionRepository->getCoInteractedProducts($product, 'view', 5);

        return $this->json([
            'product'      => ProductListItem::fromEntity($product),
            'interactions' => [
                'views'     => $interactionCounts['view'],
                'cartAdds'  => $interactionCounts['cart'],
                'purchases' => $interactionCounts['purchase'],
                'ratings'   => $interactionCounts['rating'],
            ],
            'frequentlyBoughtWith' => array_map(fn($p) => ProductListItem::fromEntity($p), $boughtWith),
            'frequentlyViewedWith' => array_map(fn($p) => ProductListItem::fromEntity($p), $viewedWith),
        ]);
    }
}
