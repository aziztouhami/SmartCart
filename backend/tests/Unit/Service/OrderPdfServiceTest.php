<?php

namespace App\Tests\Unit\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Service\OrderPdfService;
use PHPUnit\Framework\TestCase;

class OrderPdfServiceTest extends TestCase
{
    private OrderPdfService $service;

    protected function setUp(): void
    {
        $this->service = new OrderPdfService();
    }

    private function makeOrder(int $id = 1): Order
    {
        $order = new Order();
        $ref = new \ReflectionProperty(Order::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($order, $id);
        $order->setTotalAmount('99.900');
        $order->setCreatedAt(new \DateTimeImmutable('2026-01-15 10:00:00'));

        return $order;
    }

    public function testRendersValidPdfBytesForAMinimalOrder(): void
    {
        $order = $this->makeOrder();

        $pdf = $this->service->render($order);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(100, strlen($pdf));
    }

    public function testRendersWithUserAndShippingAddress(): void
    {
        $order = $this->makeOrder();
        $user = new User();
        $user->setFirstName('Jane');
        $user->setLastName('Doe');
        $user->setEmail('jane@example.com');
        $order->setUser($user);
        $order->setShippingAddress(json_encode([
            'street' => '1 Main St',
            'city' => 'Casablanca',
            'postalCode' => '20000',
            'country' => 'Morocco',
        ]));

        $pdf = $this->service->render($order);

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function testRendersWithOrderItems(): void
    {
        $order = $this->makeOrder();

        $product = new Product();
        $product->setName('Wireless Mouse');

        $item = new OrderItem();
        $item->setProduct($product);
        $item->setQuantity(2);
        $item->setPrice('19.990');
        $item->setOrder($order);
        $order->getOrderItems()->add($item);

        $pdf = $this->service->render($order);

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function testRendersWithoutShippingAddressOrUser(): void
    {
        $order = $this->makeOrder();

        // No user, no shipping address set at all — must not throw.
        $pdf = $this->service->render($order);

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function testRendersWithMalformedShippingAddressJson(): void
    {
        $order = $this->makeOrder();
        $order->setShippingAddress('not valid json');

        // json_decode failure falls back to an empty array — must not throw.
        $pdf = $this->service->render($order);

        $this->assertStringStartsWith('%PDF', $pdf);
    }
}
