<?php

namespace App\Controller\Order;

use App\DTO\Order\CheckoutRequest;
use App\DTO\Order\OrderDetail;
use App\DTO\Order\OrderListItem;
use App\DTO\Pagination\PaginatedResponse;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Service\OrderService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/orders', name: 'api_orders_')]
#[OA\Tag(name: 'Orders', description: 'Order history and checkout — requires authentication')]
class OrderController extends AbstractController
{
    public function __construct(
        private OrderRepository $orderRepository,
        private OrderService $orderService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
    ) {}

    /**
     * List the authenticated user's order history (excludes cart).
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/orders',
        operationId: 'listOrders',
        summary: "User's order history",
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10)),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated order list')]
    )]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 10)));

        $orders = $this->orderRepository->findUserOrders($user, $page, $limit);
        $total  = $this->orderRepository->countUserOrders($user);

        return $this->json(PaginatedResponse::create(
            data: array_map(fn($o) => OrderListItem::fromEntity($o), $orders),
            total: $total,
            page: $page,
            limit: $limit,
        ));
    }

    /**
     * Get the full detail of one of the authenticated user's orders.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/api/orders/{id}',
        operationId: 'getOrder',
        summary: 'Order detail',
        security: [['Bearer' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Order detail'),
            new OA\Response(response: 403, description: 'Forbidden — not your order'),
            new OA\Response(response: 404, description: 'Order not found'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $order = $this->orderRepository->find($id);
        if (!$order) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        if ($order->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        return $this->json(OrderDetail::fromEntity($order));
    }

    /**
     * Checkout: convert the current cart into a pending order.
     *
     * Provide either an addressId (saved address) or street/city/country inline.
     * Status changes: cart → pending.
     */
    #[Route('/checkout', name: 'checkout', methods: ['POST'])]
    #[OA\Post(
        path: '/api/orders/checkout',
        operationId: 'checkout',
        summary: 'Place order from cart',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'addressId', type: 'integer', nullable: true, description: 'Use a saved address'),
                    new OA\Property(property: 'street', type: 'string'),
                    new OA\Property(property: 'city', type: 'string'),
                    new OA\Property(property: 'postalCode', type: 'string'),
                    new OA\Property(property: 'country', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Order placed successfully'),
            new OA\Response(response: 400, description: 'Cart is empty or address missing'),
            new OA\Response(response: 404, description: 'Saved address not found'),
        ]
    )]
    public function checkout(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $dto = $this->serializer->deserialize($request->getContent(), CheckoutRequest::class, 'json');
        } catch (\Exception) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $order = $this->orderService->checkout($user, $dto);

        return $this->json(OrderDetail::fromEntity($order), Response::HTTP_CREATED);
    }

    /**
     * Customer self-cancel — only allowed while the order is still 'pending'.
     */
    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(
        path: '/api/orders/{id}/cancel',
        operationId: 'cancelOrder',
        summary: 'Cancel an order before it is confirmed',
        security: [['Bearer' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Order cancelled'),
            new OA\Response(response: 400, description: 'Order is no longer pending'),
            new OA\Response(response: 403, description: 'Forbidden — not your order'),
            new OA\Response(response: 404, description: 'Order not found'),
        ]
    )]
    public function cancel(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $order = $this->orderRepository->find($id);
        if (!$order) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        if ($order->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $order = $this->orderService->cancelOwnOrder($order);

        return $this->json(OrderDetail::fromEntity($order));
    }
}
