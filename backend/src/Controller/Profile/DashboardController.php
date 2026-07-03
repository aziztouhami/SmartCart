<?php

namespace App\Controller\Profile;

use App\DTO\Favorite\FavoriteItem;
use App\DTO\Order\OrderListItem;
use App\Entity\User;
use App\Repository\FavoriteRepository;
use App\Repository\OrderRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/profile/dashboard', name: 'api_profile_dashboard_')]
#[OA\Tag(name: 'Profile', description: 'Personal dashboard — requires authentication')]
class DashboardController extends AbstractController
{
    public function __construct(
        private OrderRepository $orderRepository,
        private FavoriteRepository $favoriteRepository,
    ) {}

    /**
     * Return a summary dashboard for the authenticated user:
     *   - recent orders (last 5)
     *   - order counts by status
     *   - favorites count + last 4
     */
    #[Route('', name: 'index', methods: ['GET'])]
    #[OA\Get(
        path: '/api/profile/dashboard',
        operationId: 'getDashboard',
        summary: 'Personal dashboard summary',
        security: [['Bearer' => []]],
        responses: [new OA\Response(response: 200, description: 'Dashboard data')]
    )]
    public function index(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $recentOrders    = $this->orderRepository->findUserOrders($user, 1, 5);
        $totalOrders     = $this->orderRepository->countUserOrders($user);
        $recentFavorites = $this->favoriteRepository->findByUser($user, 1, 4);
        $totalFavorites  = $this->favoriteRepository->countByUser($user);

        $statusCounts = $this->orderRepository->countUserOrdersByStatus($user);

        return $this->json([
            'orders' => [
                'total'  => $totalOrders,
                'byStatus' => $statusCounts,
                'recent' => array_map(fn($o) => OrderListItem::fromEntity($o), $recentOrders),
            ],
            'favorites' => [
                'total'  => $totalFavorites,
                'recent' => array_map(fn($f) => FavoriteItem::fromEntity($f), $recentFavorites),
            ],
        ]);
    }
}
