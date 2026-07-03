<?php

namespace App\Controller\Admin;

use App\DTO\Order\AdminOrderListItem;
use App\DTO\Order\OrderDetail;
use App\DTO\Order\UpdateOrderStatusRequest;
use App\DTO\Pagination\PaginatedResponse;
use App\Repository\OrderRepository;
use App\Service\OrderService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/admin/orders', name: 'api_admin_orders_')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Admin — Orders', description: 'Order management (ROLE_ADMIN required)')]
class OrderAdminController extends AbstractController
{
    public function __construct(
        private OrderRepository $orderRepository,
        private OrderService $orderService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
    ) {}

    /**
     * List all orders with optional status filter.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin/orders',
        operationId: 'adminListOrders',
        summary: 'List all orders',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'])),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated order list')]
    )]
    public function list(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $page   = max(1, (int) $request->query->get('page', 1));
        $limit  = min(100, max(1, (int) $request->query->get('limit', 20)));

        $orders = $this->orderRepository->findAllOrders($status ?: null, $page, $limit);
        $total  = $this->orderRepository->countAllOrders($status ?: null);

        return $this->json(PaginatedResponse::create(
            data: array_map(fn($o) => AdminOrderListItem::fromEntity($o), $orders),
            total: $total,
            page: $page,
            limit: $limit,
        ));
    }

    /**
     * Get full detail of any order.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/api/admin/orders/{id}',
        operationId: 'adminGetOrder',
        summary: 'Get order detail',
        security: [['Bearer' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Order detail'),
            new OA\Response(response: 404, description: 'Order not found'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $order = $this->orderRepository->find($id);
        if (!$order || $order->getStatus() === 'cart') {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(OrderDetail::fromEntity($order));
    }

    /**
     * Update the status of an order (follows allowed transitions).
     *
     * Allowed transitions:
     *   pending → confirmed | cancelled
     *   confirmed → shipped | cancelled
     *   shipped → delivered
     */
    #[Route('/{id}/status', name: 'update_status', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        path: '/api/admin/orders/{id}/status',
        operationId: 'adminUpdateOrderStatus',
        summary: 'Update order status',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled']),
                ]
            )
        ),
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Order updated'),
            new OA\Response(response: 400, description: 'Invalid status transition'),
            new OA\Response(response: 404, description: 'Order not found'),
        ]
    )]
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $order = $this->orderRepository->find($id);
        if (!$order || $order->getStatus() === 'cart') {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $dto = $this->serializer->deserialize($request->getContent(), UpdateOrderStatusRequest::class, 'json');
        } catch (\Exception) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        try {
            $order = $this->orderService->updateStatus($order, $dto->status);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getCode() ?: Response::HTTP_BAD_REQUEST);
        }

        return $this->json(OrderDetail::fromEntity($order));
    }
}
