<?php

namespace App\Controller\Admin;

use App\DTO\Admin\UserAdminItem;
use App\DTO\Order\OrderListItem;
use App\DTO\Pagination\PaginatedResponse;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use App\Service\UserService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/users', name: 'api_admin_users_')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Admin — Users', description: 'User management (ROLE_ADMIN required)')]
class UserAdminController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private OrderRepository $orderRepository,
        private UserService $userService,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin/users',
        operationId: 'adminListUsers',
        summary: 'List all users',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated user list')]
    )]
    public function list(Request $request): JsonResponse
    {
        $page   = max(1, (int) $request->query->get('page', 1));
        $limit  = min(100, max(1, (int) $request->query->get('limit', 20)));
        $search = $request->query->get('search') ?: null;

        $users = $this->userRepository->findAllPaginated($page, $limit, $search);
        $total = $this->userRepository->countAllUsers($search);

        $userIds     = array_map(fn($u) => $u->getId(), $users);
        $orderCounts = $this->orderRepository->countOrdersPerUser($userIds);

        return $this->json(PaginatedResponse::create(
            data: array_map(
                fn($u) => UserAdminItem::fromEntity($u, $orderCounts[$u->getId()] ?? 0),
                $users
            ),
            total: $total,
            page: $page,
            limit: $limit,
        ));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/api/admin/users/{id}',
        operationId: 'adminShowUser',
        summary: 'Get user details with recent orders',
        security: [['Bearer' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'User detail'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $orderCount   = $this->orderRepository->countUserOrders($user);
        $recentOrders = $this->orderRepository->findUserOrders($user, 1, 5);

        return $this->json([
            'user'         => UserAdminItem::fromEntity($user, $orderCount),
            'recentOrders' => array_map(fn($o) => OrderListItem::fromEntity($o), $recentOrders),
        ]);
    }

    #[Route('/{id}/role', name: 'update_role', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        path: '/api/admin/users/{id}/role',
        operationId: 'adminUpdateUserRole',
        summary: 'Promote or demote a user',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['isAdmin'],
                properties: [new OA\Property(property: 'isAdmin', type: 'boolean')]
            )
        ),
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Role updated'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function updateRole(int $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $data    = json_decode($request->getContent(), true) ?? [];
        $isAdmin = (bool) ($data['isAdmin'] ?? false);

        $user = $this->userService->updateRole($user, $isAdmin);

        return $this->json(UserAdminItem::fromEntity($user, $this->orderRepository->countUserOrders($user)));
    }
}
