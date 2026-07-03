<?php

namespace App\Tests\Unit\Service;

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
use App\Service\CartService;
use App\Service\InteractionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class CartServiceTest extends TestCase
{
    private OrderRepository $orderRepository;
    private ProductRepository $productRepository;
    private PromotionRepository $promotionRepository;
    private EntityManagerInterface $em;
    private InteractionService $interactionService;
    private CartService $service;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->promotionRepository = $this->createMock(PromotionRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->interactionService = $this->createMock(InteractionService::class);

        $this->service = new CartService(
            $this->orderRepository,
            $this->createMock(OrderItemRepository::class),
            $this->productRepository,
            $this->promotionRepository,
            $this->em,
            $this->interactionService,
        );
    }

    private function makeProduct(int $id, string $price, int $stock): Product
    {
        $product = new Product();
        $ref = new \ReflectionProperty(Product::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($product, $id);
        $product->setPrice($price);
        $product->setStock($stock);

        return $product;
    }

    public function testAddItemThrowsWhenProductNotFound(): void
    {
        $this->productRepository->method('find')->willReturn(null);

        $dto = new AddToCartRequest();
        $dto->productId = 99;
        $dto->quantity = 1;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Product not found');

        $this->service->addItem(new User(), $dto);
    }

    public function testAddItemThrowsWhenRequestedQuantityExceedsStock(): void
    {
        $product = $this->makeProduct(1, '10.00', 3);
        $this->productRepository->method('find')->willReturn($product);
        $this->orderRepository->method('findCartByUser')->willReturn(null);
        $this->promotionRepository->method('findActiveForProduct')->willReturn(null);

        $dto = new AddToCartRequest();
        $dto->productId = 1;
        $dto->quantity = 5;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only 3 items available in stock');

        $this->service->addItem(new User(), $dto);
    }

    public function testAddItemCreatesCartAndItemWhenNoneExist(): void
    {
        $product = $this->makeProduct(1, '10.00', 5);
        $this->productRepository->method('find')->willReturn($product);
        $this->orderRepository->method('findCartByUser')->willReturn(null);
        $this->promotionRepository->method('findActiveForProduct')->willReturn(null);
        $this->interactionService->expects($this->once())->method('track');

        $dto = new AddToCartRequest();
        $dto->productId = 1;
        $dto->quantity = 2;

        $cart = $this->service->addItem(new User(), $dto);

        $this->assertSame('cart', $cart->getStatus());
        $this->assertCount(1, $cart->getOrderItems());
        $this->assertSame(2, $cart->getOrderItems()->first()->getQuantity());
        $this->assertSame('20', $cart->getTotalAmount());
    }

    public function testAddItemMergesQuantityWhenProductAlreadyInCart(): void
    {
        $product = $this->makeProduct(1, '10.00', 5);

        $cart = new Order();
        $existingItem = new OrderItem();
        $existingItem->setProduct($product);
        $existingItem->setQuantity(2);
        $existingItem->setPrice('10.00');
        $cart->addOrderItem($existingItem);

        $this->productRepository->method('find')->willReturn($product);
        $this->orderRepository->method('findCartByUser')->willReturn($cart);
        $this->promotionRepository->method('findActiveForProduct')->willReturn(null);

        $dto = new AddToCartRequest();
        $dto->productId = 1;
        $dto->quantity = 2;

        $result = $this->service->addItem(new User(), $dto);

        $this->assertCount(1, $result->getOrderItems());
        $this->assertSame(4, $result->getOrderItems()->first()->getQuantity());
    }

    public function testUpdateItemThrowsWhenQuantityExceedsStock(): void
    {
        $product = $this->makeProduct(1, '10.00', 2);
        $item = new OrderItem();
        $item->setProduct($product);
        $item->setQuantity(1);
        $item->setOrder(new Order());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only 2 items available in stock');

        $this->service->updateItem($item, 5);
    }

    public function testUpdateItemRecalculatesCartTotal(): void
    {
        $product = $this->makeProduct(1, '10.00', 5);
        $this->promotionRepository->method('findActiveForProduct')->willReturn(null);

        $cart = new Order();
        $item = new OrderItem();
        $item->setProduct($product);
        $item->setQuantity(1);
        $item->setPrice('10.00');
        $cart->addOrderItem($item);

        $result = $this->service->updateItem($item, 3);

        $this->assertSame(3, $item->getQuantity());
        $this->assertSame('30', $result->getTotalAmount());
    }

    public function testRemoveItemRecalculatesTotalAndRemovesEntity(): void
    {
        $product = $this->makeProduct(1, '10.00', 5);

        $cart = new Order();
        $remaining = new OrderItem();
        $remaining->setProduct($product);
        $remaining->setQuantity(2);
        $remaining->setPrice('10.00');
        $cart->addOrderItem($remaining);

        $toRemove = new OrderItem();
        $toRemove->setProduct($product);
        $toRemove->setQuantity(1);
        $toRemove->setPrice('10.00');
        $cart->addOrderItem($toRemove);

        $this->em->expects($this->once())->method('remove')->with($toRemove);

        $result = $this->service->removeItem($toRemove);

        $this->assertSame('20', $result->getTotalAmount());
    }

    public function testClearCartRemovesAllItemsAndZeroesTotal(): void
    {
        $product = $this->makeProduct(1, '10.00', 5);
        $cart = new Order();
        $item = new OrderItem();
        $item->setProduct($product);
        $item->setQuantity(2);
        $item->setPrice('10.00');
        $cart->addOrderItem($item);

        $this->orderRepository->method('findCartByUser')->willReturn($cart);
        $this->em->expects($this->once())->method('remove')->with($item);

        $result = $this->service->clearCart(new User());

        $this->assertSame('0.00', $result->getTotalAmount());
    }

    public function testClearCartReturnsNullWhenNoCartExists(): void
    {
        $this->orderRepository->method('findCartByUser')->willReturn(null);
        $this->em->expects($this->never())->method('remove');

        $this->assertNull($this->service->clearCart(new User()));
    }

    public function testSyncCartSkipsProductsOutOfStock(): void
    {
        $outOfStock = $this->makeProduct(1, '10.00', 0);
        $this->productRepository->method('find')->willReturn($outOfStock);
        $this->orderRepository->method('findCartByUser')->willReturn(null);

        $dto = new SyncCartRequest();
        $dto->items = [['productId' => 1, 'quantity' => 2]];
        $dto->strategy = 'merge';

        $cart = $this->service->syncCart(new User(), $dto);

        $this->assertCount(0, $cart->getOrderItems());
    }

    public function testSyncCartMergeTakesMaxOfExistingAndIncomingQuantity(): void
    {
        $product = $this->makeProduct(1, '10.00', 10);
        $this->promotionRepository->method('findActiveForProduct')->willReturn(null);

        $cart = new Order();
        $existingItem = new OrderItem();
        $existingItem->setProduct($product);
        $existingItem->setQuantity(5);
        $existingItem->setPrice('10.00');
        $cart->addOrderItem($existingItem);

        $this->productRepository->method('find')->willReturn($product);
        $this->orderRepository->method('findCartByUser')->willReturn($cart);

        $dto = new SyncCartRequest();
        $dto->items = [['productId' => 1, 'quantity' => 2]];
        $dto->strategy = 'merge';

        $result = $this->service->syncCart(new User(), $dto);

        $this->assertCount(1, $result->getOrderItems());
        $this->assertSame(5, $result->getOrderItems()->first()->getQuantity());
    }
}
