<?php

namespace App\Controller\Recommendation;

use App\DTO\Product\ProductListItem;
use App\Entity\User;
use App\Service\Recommendation\RecommendationServingService;
use App\Repository\PromotionRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/recommendations', name: 'api_recommendations_')]
#[OA\Tag(name: 'Recommendations', description: 'Hybrid (logged-in) / session-based (guest) product recommendations')]
class RecommendationController extends AbstractController
{
    public function __construct(
        private RecommendationServingService $recommendationServing,
        private PromotionRepository $promotionRepository,
    ) {}

    #[Route('', name: 'get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/recommendations',
        operationId: 'getRecommendations',
        summary: 'Get personalized/session-based product recommendations',
        description: 'Falls back to a "new visitors + trending" list when there is no browsing history yet, so this is only empty if the catalog itself has nothing in stock.',
        parameters: [
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 8)),
            new OA\Parameter(name: 'X-Session-Id', in: 'header', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Recommended products, most relevant first')]
    )]
    public function get(Request $request): JsonResponse
    {
        $limit = min(20, max(1, (int) $request->query->get('limit', 8)));

        $user = $this->getUser();
        $ordered = $user instanceof User
            ? $this->recommendationServing->forUser($user, $limit)
            : $this->recommendationServing->forGuest($request, $limit);

        $promoMap = $this->promotionRepository->findActiveForProducts($ordered);

        return $this->json([
            'recommendations' => array_map(
                fn ($p) => ProductListItem::fromEntity($p, $promoMap[$p->getId()] ?? null),
                $ordered
            ),
        ]);
    }

    #[Route('/product/{id}', name: 'for_product', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/api/recommendations/product/{id}',
        operationId: 'getProductRecommendations',
        summary: 'Get "similar" and "complementary" recommendations for a product detail page',
        description: 'Returns at most 4 similar products and at most 4 complementary (frequently bought together) products.',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Similar and complementary product lists, max 4 each')]
    )]
    public function forProduct(int $id): JsonResponse
    {
        $lists = $this->recommendationServing->forProduct($id, 4);
        $promoMap = $this->promotionRepository->findActiveForProducts([...$lists['similar'], ...$lists['complementary']]);

        $toDto = fn ($products) => array_map(
            fn ($p) => ProductListItem::fromEntity($p, $promoMap[$p->getId()] ?? null),
            $products
        );

        return $this->json([
            'similar' => $toDto($lists['similar']),
            'complementary' => $toDto($lists['complementary']),
        ]);
    }
}
