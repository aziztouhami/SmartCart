<?php

namespace App\Controller\Profile;

use App\DTO\Favorite\FavoriteItem;
use App\DTO\Pagination\PaginatedResponse;
use App\Entity\User;
use App\Repository\FavoriteRepository;
use App\Service\FavoriteService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/profile/favorites', name: 'api_profile_favorites_')]
#[OA\Tag(name: 'Profile', description: 'Wishlist / favorites — requires authentication')]
class FavoriteController extends AbstractController
{
    public function __construct(
        private FavoriteRepository $favoriteRepository,
        private FavoriteService $favoriteService,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/profile/favorites',
        operationId: 'listFavorites',
        summary: 'List favorites (wishlist)',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated favorites')]
    )]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));

        $favorites = $this->favoriteRepository->findByUser($user, $page, $limit);
        $total     = $this->favoriteRepository->countByUser($user);

        return $this->json(PaginatedResponse::create(
            data: array_map(fn($f) => FavoriteItem::fromEntity($f), $favorites),
            total: $total,
            page: $page,
            limit: $limit,
        ));
    }

    #[Route('', name: 'add', methods: ['POST'])]
    #[OA\Post(
        path: '/api/profile/favorites',
        operationId: 'addFavorite',
        summary: 'Add product to favorites',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['productId'],
                properties: [new OA\Property(property: 'productId', type: 'integer')]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Added to favorites'),
            new OA\Response(response: 409, description: 'Already in favorites'),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    public function add(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $data      = json_decode($request->getContent(), true) ?? [];
        $productId = (int) ($data['productId'] ?? 0);

        $favorite = $this->favoriteService->add($user, $productId);

        return $this->json(FavoriteItem::fromEntity($favorite), Response::HTTP_CREATED);
    }

    #[Route('/{productId}', name: 'remove', methods: ['DELETE'], requirements: ['productId' => '\d+'])]
    #[OA\Delete(
        path: '/api/profile/favorites/{productId}',
        operationId: 'removeFavorite',
        summary: 'Remove product from favorites',
        security: [['Bearer' => []]],
        parameters: [new OA\Parameter(name: 'productId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Removed'),
            new OA\Response(response: 404, description: 'Not in favorites'),
        ]
    )]
    public function remove(int $productId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $this->favoriteService->remove($user, $productId);

        return $this->json(['message' => 'Removed from favorites']);
    }
}
