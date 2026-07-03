<?php

namespace App\Service;

use App\DTO\Cart\AddToCartRequest;
use App\DTO\Cart\SyncCartRequest;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\OrderItemRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\PromotionRepository;
use Doctrine\ORM\EntityManagerInterface;

class CartService
{
    public function __construct(
        private OrderRepository $orderRepository,
        private OrderItemRepository $orderItemRepository,
        private ProductRepository $productRepository,
        private PromotionRepository $promotionRepository,
        private EntityManagerInterface $em,
        private InteractionService $interactionService,
    ) {}

    public function getCart(User $user): ?Order
    {
        return $this->orderRepository->findCartByUser($user);
    }

    /**
     * Current price to charge for a product — the active promotion's
     * discounted price if one applies, otherwise the regular price.
     */
    private function effectivePrice(Product $product): string
    {
        $promotion = $this->promotionRepository->findActiveForProduct($product);
        if (!$promotion) {
            return $product->getPrice();
        }

        return (string) $promotion->computePrice((float) $product->getPrice());
    }

    public function addItem(User $user, AddToCartRequest $dto): Order
    {
        $product = $this->productRepository->find($dto->productId);
        if (!$product) {
            throw new \RuntimeException('Product not found', 404);
        }

        $cart = $this->orderRepository->findCartByUser($user) ?? $this->createCart($user);

        $existingItem = null;
        foreach ($cart->getOrderItems() as $item) {
            if ($item->getProduct()->getId() === $product->getId()) {
                $existingItem = $item;
                break;
            }
        }

        $newQty = ($existingItem?->getQuantity() ?? 0) + $dto->quantity;
        if ($newQty > $product->getStock()) {
            throw new \RuntimeException("Only {$product->getStock()} items available in stock", 400);
        }

        if ($existingItem) {
            $existingItem->setQuantity($newQty);
            $existingItem->setPrice($this->effectivePrice($product));
        } else {
            $item = new OrderItem();
            $item->setProduct($product);
            $item->setQuantity($dto->quantity);
            $item->setPrice($this->effectivePrice($product));
            $cart->addOrderItem($item);
            $this->em->persist($item);
        }

        $this->recalculateTotal($cart);
        $this->em->flush();

        // Real cart-add signal for the recommender, independent of whether the
        // frontend also calls the manual /interact endpoint.
        $this->interactionService->track($user, $product, 'cart', $dto->quantity);

        return $cart;
    }

    public function updateItem(OrderItem $item, int $quantity): Order
    {
        if ($quantity > $item->getProduct()->getStock()) {
            throw new \RuntimeException("Only {$item->getProduct()->getStock()} items available in stock", 400);
        }

        $item->setQuantity($quantity);
        $item->setPrice($this->effectivePrice($item->getProduct()));
        $cart = $item->getOrder();
        $this->recalculateTotal($cart);
        $this->em->flush();

        return $cart;
    }

    public function removeItem(OrderItem $item): Order
    {
        $cart = $item->getOrder();
        $cart->removeOrderItem($item);
        $this->em->remove($item);
        $this->recalculateTotal($cart);
        $this->em->flush();

        return $cart;
    }

    public function clearCart(User $user): ?Order
    {
        $cart = $this->orderRepository->findCartByUser($user);
        if ($cart) {
            foreach ($cart->getOrderItems() as $item) {
                $this->em->remove($item);
            }
            $cart->setTotalAmount('0.00');
            $this->em->flush();
        }

        return $cart;
    }

    public function syncCart(User $user, SyncCartRequest $dto): Order
    {
        $cart = $this->orderRepository->findCartByUser($user) ?? $this->createCart($user);

        if ($dto->strategy === 'replace') {
            foreach ($cart->getOrderItems() as $item) {
                $this->em->remove($item);
            }
            $this->em->flush();
        }

        foreach ($dto->items as $incoming) {
            $productId = (int) ($incoming['productId'] ?? 0);
            $qty = max(1, (int) ($incoming['quantity'] ?? 1));

            $product = $this->productRepository->find($productId);
            if (!$product || $product->getStock() < 1) {
                continue;
            }

            $existingItem = null;
            foreach ($cart->getOrderItems() as $ci) {
                if ($ci->getProduct()->getId() === $product->getId()) {
                    $existingItem = $ci;
                    break;
                }
            }

            if ($existingItem) {
                // max (not sum) so re-syncing an already-merged snapshot (e.g. on
                // every login) stays idempotent instead of growing the quantity.
                $merged = min(max($existingItem->getQuantity(), $qty), $product->getStock());
                $existingItem->setQuantity($merged);
                $existingItem->setPrice($this->effectivePrice($product));
            } else {
                $item = new OrderItem();
                $item->setProduct($product);
                $item->setQuantity(min($qty, $product->getStock()));
                $item->setPrice($this->effectivePrice($product));
                $cart->addOrderItem($item);
                $this->em->persist($item);
            }
        }

        $this->recalculateTotal($cart);
        $this->em->flush();

        return $cart;
    }

    private function createCart(User $user): Order
    {
        $cart = new Order();
        $cart->setUser($user);
        $cart->setStatus('cart');
        $cart->setTotalAmount('0.00');
        $this->em->persist($cart);
        return $cart;
    }

    private function recalculateTotal(Order $cart): void
    {
        $total = 0.0;
        foreach ($cart->getOrderItems() as $item) {
            $total += (float) $item->getPrice() * $item->getQuantity();
        }
        $cart->setTotalAmount((string) round($total, 2));
    }
}
