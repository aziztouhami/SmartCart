<?php

namespace App\Tests\Unit\Service;

use App\Entity\Product;
use App\Repository\GuestEventRepository;
use App\Repository\InteractionRepository;
use App\Repository\OrderRepository;
use App\Service\ProductActivityService;
use PHPUnit\Framework\TestCase;

class ProductActivityServiceTest extends TestCase
{
    public function testCombinesLoggedInAndGuestViewingCounts(): void
    {
        $product = new Product();
        $interactionRepository = $this->createMock(InteractionRepository::class);
        $guestEventRepository = $this->createMock(GuestEventRepository::class);
        $orderRepository = $this->createMock(OrderRepository::class);

        $interactionRepository->method('countDistinctUsersViewingSince')->willReturn(3);
        $guestEventRepository->method('countDistinctSessionsViewingSince')->willReturn(5);
        $orderRepository->method('countActiveCartsContainingProduct')->willReturn(7);

        $service = new ProductActivityService($interactionRepository, $guestEventRepository, $orderRepository);
        $activity = $service->getActivity($product);

        $this->assertSame(8, $activity['viewingNow']);
        $this->assertSame(7, $activity['inCarts']);
    }

    public function testReturnsZeroesForAQuietProduct(): void
    {
        $product = new Product();
        $interactionRepository = $this->createMock(InteractionRepository::class);
        $guestEventRepository = $this->createMock(GuestEventRepository::class);
        $orderRepository = $this->createMock(OrderRepository::class);

        $interactionRepository->method('countDistinctUsersViewingSince')->willReturn(0);
        $guestEventRepository->method('countDistinctSessionsViewingSince')->willReturn(0);
        $orderRepository->method('countActiveCartsContainingProduct')->willReturn(0);

        $service = new ProductActivityService($interactionRepository, $guestEventRepository, $orderRepository);
        $activity = $service->getActivity($product);

        $this->assertSame(0, $activity['viewingNow']);
        $this->assertSame(0, $activity['inCarts']);
    }

    public function testUsesATenMinuteViewingWindow(): void
    {
        $product = new Product();
        $interactionRepository = $this->createMock(InteractionRepository::class);
        $guestEventRepository = $this->createMock(GuestEventRepository::class);
        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->method('countActiveCartsContainingProduct')->willReturn(0);

        $interactionRepository->expects($this->once())
            ->method('countDistinctUsersViewingSince')
            ->with($product, $this->callback(function (\DateTimeImmutable $since) {
                $diffMinutes = (new \DateTimeImmutable())->getTimestamp() - $since->getTimestamp();

                // Should be ~10 minutes ago, allow a couple seconds of test execution slack.
                return $diffMinutes >= 598 && $diffMinutes <= 602;
            }))
            ->willReturn(0);
        $guestEventRepository->method('countDistinctSessionsViewingSince')->willReturn(0);

        $service = new ProductActivityService($interactionRepository, $guestEventRepository, $orderRepository);
        $service->getActivity($product);
    }
}
