<?php

namespace App\Controller\Cart;

use App\DTO\Cart\AddToCartRequest;
use App\DTO\Cart\CartResponse;
use App\DTO\Cart\SyncCartRequest;
use App\DTO\Cart\UpdateCartItemRequest;
use App\Entity\User;
use App\Repository\OrderItemRepository;
use App\Service\CartService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/cart', name: 'api_cart_')]
#[OA\Tag(name: 'Cart', description: 'Persistent cart — requires authentication')]
class CartController extends AbstractController
{
    public function __construct(
        private CartService $cartService,
        private OrderItemRepository $orderItemRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
    ) {}

    /**
     * Get the current user's cart.
     */
    #[Route('', name: 'get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/cart',
        operationId: 'getCart',
        summary: 'Get current cart',
        security: [['Bearer' => []]],
        responses: [new OA\Response(response: 200, description: 'Current cart')]
    )]
    public function get(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $cart = $this->cartService->getCart($user);
        return $this->json(CartResponse::fromOrder($cart));
    }

    /**
     * Add a product to the cart (or increase quantity if already present).
     */
    #[Route('/items', name: 'add_item', methods: ['POST'])]
    #[OA\Post(
        path: '/api/cart/items',
        operationId: 'addToCart',
        summary: 'Add item to cart',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['productId', 'quantity'],
                properties: [
                    new OA\Property(property: 'productId', type: 'integer'),
                    new OA\Property(property: 'quantity', type: 'integer', minimum: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Updated cart'),
            new OA\Response(response: 400, description: 'Validation error or insufficient stock'),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    public function addItem(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $dto = $this->serializer->deserialize($request->getContent(), AddToCartRequest::class, 'json');
        } catch (\Exception) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $cart = $this->cartService->addItem($user, $dto);

        return $this->json(CartResponse::fromOrder($cart));
    }

    /**
     * Update the quantity of a cart item.
     */
    #[Route('/items/{itemId}', name: 'update_item', methods: ['PUT'], requirements: ['itemId' => '\d+'])]
    #[OA\Put(
        path: '/api/cart/items/{itemId}',
        operationId: 'updateCartItem',
        summary: 'Update cart item quantity',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [new OA\Property(property: 'quantity', type: 'integer', minimum: 1)]
            )
        ),
        parameters: [new OA\Parameter(name: 'itemId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Updated cart'),
            new OA\Response(response: 403, description: 'Item does not belong to your cart'),
            new OA\Response(response: 404, description: 'Item not found'),
        ]
    )]
    public function updateItem(int $itemId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $dto = $this->serializer->deserialize($request->getContent(), UpdateCartItemRequest::class, 'json');
        } catch (\Exception) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $item = $this->orderItemRepository->find($itemId);
        if (!$item) {
            return $this->json(['error' => 'Cart item not found'], Response::HTTP_NOT_FOUND);
        }

        $cart = $item->getOrder();
        if ($cart->getUser()->getId() !== $user->getId() || $cart->getStatus() !== 'cart') {
            return $this->json(['error' => 'Item does not belong to your cart'], Response::HTTP_FORBIDDEN);
        }

        $cart = $this->cartService->updateItem($item, $dto->quantity);

        return $this->json(CartResponse::fromOrder($cart));
    }

    /**
     * Remove a specific item from the cart.
     */
    #[Route('/items/{itemId}', name: 'remove_item', methods: ['DELETE'], requirements: ['itemId' => '\d+'])]
    #[OA\Delete(
        path: '/api/cart/items/{itemId}',
        operationId: 'removeCartItem',
        summary: 'Remove item from cart',
        security: [['Bearer' => []]],
        parameters: [new OA\Parameter(name: 'itemId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Updated cart'),
            new OA\Response(response: 403, description: 'Item does not belong to your cart'),
            new OA\Response(response: 404, description: 'Item not found'),
        ]
    )]
    public function removeItem(int $itemId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $item = $this->orderItemRepository->find($itemId);
        if (!$item) {
            return $this->json(['error' => 'Cart item not found'], Response::HTTP_NOT_FOUND);
        }

        $cart = $item->getOrder();
        if ($cart->getUser()->getId() !== $user->getId() || $cart->getStatus() !== 'cart') {
            return $this->json(['error' => 'Item does not belong to your cart'], Response::HTTP_FORBIDDEN);
        }

        $cart = $this->cartService->removeItem($item);
        return $this->json(CartResponse::fromOrder($cart));
    }

    /**
     * Empty the cart (remove all items but keep the cart row).
     */
    #[Route('', name: 'clear', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/cart',
        operationId: 'clearCart',
        summary: 'Clear entire cart',
        security: [['Bearer' => []]],
        responses: [new OA\Response(response: 200, description: 'Empty cart')]
    )]
    public function clear(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $cart = $this->cartService->clearCart($user);
        return $this->json(CartResponse::fromOrder($cart));
    }

    /**
     * Sync localStorage cart with the DB cart.
     * Strategy 'merge' adds items on top; 'replace' wipes the DB cart first.
     */
    #[Route('/sync', name: 'sync', methods: ['POST'])]
    #[OA\Post(
        path: '/api/cart/sync',
        operationId: 'syncCart',
        summary: 'Sync localStorage cart with server',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['items'],
                properties: [
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'productId', type: 'integer'),
                                new OA\Property(property: 'quantity', type: 'integer'),
                            ]
                        )
                    ),
                    new OA\Property(property: 'strategy', type: 'string', enum: ['merge', 'replace'], default: 'merge'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Merged/replaced cart')]
    )]
    public function sync(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $dto = $this->serializer->deserialize($request->getContent(), SyncCartRequest::class, 'json');
        } catch (\Exception) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $cart = $this->cartService->syncCart($user, $dto);
        return $this->json(CartResponse::fromOrder($cart));
    }
}
