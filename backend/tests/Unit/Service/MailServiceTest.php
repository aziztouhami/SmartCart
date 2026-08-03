<?php

namespace App\Tests\Unit\Service;

use App\Entity\Brand;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\Promotion;
use App\Entity\User;
use App\Service\MailService;
use App\Service\OrderPdfService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailServiceTest extends TestCase
{
    private MailerInterface $mailer;
    private OrderPdfService $orderPdfService;
    private MailService $service;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->orderPdfService = $this->createMock(OrderPdfService::class);

        $this->service = new MailService(
            $this->mailer,
            $this->orderPdfService,
            'http://localhost:3000',
            'admin@smartcart.local',
        );
    }

    private function makeUser(string $email = 'user@example.com'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Jane');
        $user->setLastName('Doe');

        return $user;
    }

    private function makeOrder(?User $user, int $id = 1): Order
    {
        $order = new Order();
        $ref = new \ReflectionProperty(Order::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($order, $id);
        $order->setTotalAmount('99.90');
        if ($user) {
            $order->setUser($user);
        }

        return $order;
    }

    public function testSendSafelyInvokesTheCallback(): void
    {
        $called = false;
        $this->service->sendSafely(function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
    }

    public function testSendSafelySwallowsExceptions(): void
    {
        $this->service->sendSafely(function () {
            throw new \RuntimeException('SMTP down');
        });

        // No exception propagated — the assertion is simply that we got here.
        $this->addToAssertionCount(1);
    }

    public function testSendVerificationEmailIncludesTokenLink(): void
    {
        $user = $this->makeUser();
        $user->setVerificationToken('abc123');

        $this->mailer->expects($this->once())->method('send')->with($this->callback(function (Email $email) {
            $this->assertSame('user@example.com', $email->getTo()[0]->getAddress());
            $this->assertSame('Confirm your SmartCart account', $email->getSubject());
            $this->assertStringContainsString('http://localhost:3000/verify-email?token=abc123', $email->getHtmlBody());

            return true;
        }));

        $this->service->sendVerificationEmail($user);
    }

    public function testSendOrderConfirmationSkipsWhenOrderHasNoUser(): void
    {
        $order = $this->makeOrder(null);

        $this->mailer->expects($this->never())->method('send');

        $this->service->sendOrderConfirmation($order);
    }

    public function testSendOrderConfirmationAttachesPdf(): void
    {
        $order = $this->makeOrder($this->makeUser());
        $this->orderPdfService->method('render')->willReturn('%PDF-1.4 fake pdf content');

        $this->mailer->expects($this->once())->method('send')->with($this->callback(function (Email $email) {
            $this->assertSame('Order #1 confirmed', $email->getSubject());
            $this->assertCount(1, $email->getAttachments());

            return true;
        }));

        $this->service->sendOrderConfirmation($order);
    }

    public function testSendOrderShippedSkipsWhenOrderHasNoUser(): void
    {
        $order = $this->makeOrder(null);

        $this->mailer->expects($this->never())->method('send');

        $this->service->sendOrderShipped($order);
    }

    public function testSendOrderShippedSetsExpectedSubject(): void
    {
        $order = $this->makeOrder($this->makeUser());

        $this->mailer->expects($this->once())->method('send')->with($this->callback(function (Email $email) {
            $this->assertSame('Order #1 has shipped', $email->getSubject());

            return true;
        }));

        $this->service->sendOrderShipped($order);
    }

    public function testSendOrderDeliveredSkipsWhenOrderHasNoUser(): void
    {
        $order = $this->makeOrder(null);

        $this->mailer->expects($this->never())->method('send');

        $this->service->sendOrderDelivered($order);
    }

    public function testSendOrderDeliveredSetsExpectedSubject(): void
    {
        $order = $this->makeOrder($this->makeUser());

        $this->mailer->expects($this->once())->method('send')->with($this->callback(function (Email $email) {
            $this->assertSame('Order #1 delivered — thank you!', $email->getSubject());

            return true;
        }));

        $this->service->sendOrderDelivered($order);
    }

    public function testSendPromotionEmailForSpecificProduct(): void
    {
        $user = $this->makeUser();
        $product = new Product();
        $ref = new \ReflectionProperty(Product::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($product, 5);
        $product->setName('iPhone 15');

        $promotion = new Promotion();
        $promotion->setType(Promotion::TYPE_PRODUCT);
        $promotion->setProduct($product);
        $promotion->setDiscountType(Promotion::DISCOUNT_PERCENTAGE);
        $promotion->setPercentage('20');

        $this->mailer->expects($this->once())->method('send')->with($this->callback(function (Email $email) {
            $this->assertSame('New promotion: iPhone 15', $email->getSubject());

            return true;
        }));

        $this->service->sendPromotionEmail($user, $promotion);
    }

    public function testSendPromotionEmailForBrand(): void
    {
        $user = $this->makeUser();
        $brand = new Brand();
        $ref = new \ReflectionProperty(Brand::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($brand, 3);
        $brand->setName('Apple');

        $promotion = new Promotion();
        $promotion->setType(Promotion::TYPE_BRAND);
        $promotion->setBrand($brand);
        $promotion->setDiscountType(Promotion::DISCOUNT_FIXED);
        $promotion->setFixedPrice('50');

        $this->mailer->expects($this->once())->method('send')->with($this->callback(function (Email $email) {
            $this->assertSame('New promotion on Apple', $email->getSubject());

            return true;
        }));

        $this->service->sendPromotionEmail($user, $promotion);
    }

    public function testSendPromotionEmailForStoreWide(): void
    {
        $user = $this->makeUser();

        $promotion = new Promotion();
        $promotion->setType(Promotion::TYPE_ALL);
        $promotion->setDiscountType(Promotion::DISCOUNT_PERCENTAGE);
        $promotion->setPercentage('10');

        $this->mailer->expects($this->once())->method('send')->with($this->callback(function (Email $email) {
            $this->assertSame('A new store-wide promotion just started!', $email->getSubject());

            return true;
        }));

        $this->service->sendPromotionEmail($user, $promotion);
    }
}
