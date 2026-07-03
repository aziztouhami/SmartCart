<?php

namespace App\Tests\Unit\Service;

use App\DTO\Order\CheckoutRequest;
use App\Entity\Address;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\AddressRepository;
use App\Repository\OrderRepository;
use App\Service\InteractionService;
use App\Service\MailService;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OrderServiceTest extends TestCase
{
    private OrderRepository $orderRepository;
    private AddressRepository $addressRepository;
    private EntityManagerInterface $em;
    private MailService $mailService;
    private InteractionService $interactionService;
    private OrderService $service;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->addressRepository = $this->createMock(AddressRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->mailService = $this->createMock(MailService::class);
        $this->interactionService = $this->createMock(InteractionService::class);

        $this->service = new OrderService(
            $this->orderRepository,
            $this->addressRepository,
            $this->em,
            $this->mailService,
            $this->interactionService,
        );
    }

    private function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setAccessible(true);
        $ref->setValue($entity, $id);
    }

    private function cartWithOneItem(): Order
    {
        $product = new Product();
        $this->setId($product, 1);
        $product->setPrice('10.00');
        $product->setStock(5);

        $item = new OrderItem();
        $item->setProduct($product);
        $item->setQuantity(2);
        $item->setPrice('10.00');

        $cart = new Order();
        $cart->addOrderItem($item);

        return $cart;
    }

    public function testCheckoutThrowsWhenCartIsEmpty(): void
    {
        $this->orderRepository->method('findCartByUser')->willReturn(null);

        $dto = new CheckoutRequest();
        $dto->contactPhone = '123456';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Your cart is empty');

        $this->service->checkout(new User(), $dto);
    }

    public function testCheckoutThrowsWhenNeitherAddressIdNorRawAddressGiven(): void
    {
        $this->orderRepository->method('findCartByUser')->willReturn($this->cartWithOneItem());

        $dto = new CheckoutRequest();
        $dto->contactPhone = '123456';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Provide either addressId or street + city + country');

        $this->service->checkout(new User(), $dto);
    }

    public function testCheckoutThrowsWhenAddressBelongsToAnotherUser(): void
    {
        $this->orderRepository->method('findCartByUser')->willReturn($this->cartWithOneItem());

        $owner = new User();
        $this->setId($owner, 2);
        $address = new Address();
        $address->setUser($owner);
        $this->addressRepository->method('find')->willReturn($address);

        $user = new User();
        $this->setId($user, 1);

        $dto = new CheckoutRequest();
        $dto->addressId = 99;
        $dto->contactPhone = '123456';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Address not found');

        $this->service->checkout($user, $dto);
    }

    public function testCheckoutSucceedsWithRawAddressAndTracksPurchaseInteractions(): void
    {
        $cart = $this->cartWithOneItem();
        $this->orderRepository->method('findCartByUser')->willReturn($cart);
        $this->interactionService->expects($this->once())->method('track')
            ->with($this->isInstanceOf(User::class), $this->isInstanceOf(Product::class), 'purchase', 2);
        $this->mailService->expects($this->once())->method('sendOrderConfirmation');

        $dto = new CheckoutRequest();
        $dto->street = '123 Main St';
        $dto->city = 'Springfield';
        $dto->country = 'US';
        $dto->contactPhone = '123456';

        $result = $this->service->checkout(new User(), $dto);

        $this->assertSame('pending', $result->getStatus());
        $this->assertNotNull($result->getShippingAddress());
    }

    public function testCheckoutSucceedsEvenWhenOrderConfirmationEmailFails(): void
    {
        $cart = $this->cartWithOneItem();
        $this->orderRepository->method('findCartByUser')->willReturn($cart);
        $this->mailService->method('sendOrderConfirmation')->willThrowException(new \RuntimeException('SMTP down'));

        $dto = new CheckoutRequest();
        $dto->street = '123 Main St';
        $dto->city = 'Springfield';
        $dto->country = 'US';
        $dto->contactPhone = '123456';

        $result = $this->service->checkout(new User(), $dto);

        $this->assertSame('pending', $result->getStatus());
    }

    public function testCancelOwnOrderSucceedsWhenPending(): void
    {
        $order = new Order();
        $order->setStatus('pending');

        $result = $this->service->cancelOwnOrder($order);

        $this->assertSame('cancelled', $result->getStatus());
    }

    public function testCancelOwnOrderThrowsWhenNotPending(): void
    {
        $order = new Order();
        $order->setStatus('confirmed');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only pending orders can be cancelled');

        $this->service->cancelOwnOrder($order);
    }

    #[DataProvider('validTransitionProvider')]
    public function testUpdateStatusAllowsValidTransitions(string $from, string $to): void
    {
        $order = new Order();
        $order->setStatus($from);

        $result = $this->service->updateStatus($order, $to);

        $this->assertSame($to, $result->getStatus());
    }

    public static function validTransitionProvider(): array
    {
        return [
            'pending to confirmed' => ['pending', 'confirmed'],
            'pending to cancelled' => ['pending', 'cancelled'],
            'confirmed to shipped' => ['confirmed', 'shipped'],
            'confirmed to cancelled' => ['confirmed', 'cancelled'],
            'shipped to delivered' => ['shipped', 'delivered'],
        ];
    }

    #[DataProvider('invalidTransitionProvider')]
    public function testUpdateStatusRejectsInvalidTransitions(string $from, string $to): void
    {
        $order = new Order();
        $order->setStatus($from);

        $this->expectException(\RuntimeException::class);

        $this->service->updateStatus($order, $to);
    }

    public static function invalidTransitionProvider(): array
    {
        return [
            'delivered is terminal' => ['delivered', 'cancelled'],
            'cancelled is terminal' => ['cancelled', 'pending'],
            'cannot skip confirmed' => ['pending', 'shipped'],
            'cannot go backwards' => ['shipped', 'confirmed'],
        ];
    }

    public function testUpdateStatusSendsShippedEmail(): void
    {
        $order = new Order();
        $order->setStatus('confirmed');

        $this->mailService->expects($this->once())->method('sendOrderShipped');
        $this->mailService->expects($this->never())->method('sendOrderDelivered');

        $this->service->updateStatus($order, 'shipped');
    }

    public function testUpdateStatusSendsDeliveredEmail(): void
    {
        $order = new Order();
        $order->setStatus('shipped');

        $this->mailService->expects($this->once())->method('sendOrderDelivered');

        $this->service->updateStatus($order, 'delivered');
    }

    public function testUpdateStatusSucceedsEvenWhenEmailFails(): void
    {
        $order = new Order();
        $order->setStatus('confirmed');
        $this->mailService->method('sendOrderShipped')->willThrowException(new \RuntimeException('SMTP down'));

        $result = $this->service->updateStatus($order, 'shipped');

        $this->assertSame('shipped', $result->getStatus());
    }
}
