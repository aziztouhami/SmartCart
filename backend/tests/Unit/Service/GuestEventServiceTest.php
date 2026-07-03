<?php

namespace App\Tests\Unit\Service;

use App\Entity\GuestEvent;
use App\Entity\Product;
use App\Service\GuestEventService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class GuestEventServiceTest extends TestCase
{
    public function testTrackPersistsEventWithGivenFields(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(GuestEvent::class));
        $em->expects($this->once())->method('flush');

        $service = new GuestEventService($em);
        $product = new Product();

        $event = $service->track('session-123', $product, 'view');

        $this->assertSame('session-123', $event->getSessionId());
        $this->assertSame($product, $event->getProduct());
        $this->assertSame('view', $event->getType());
    }

    public function testTrackRejectsInvalidType(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $service = new GuestEventService($em);

        $this->expectException(\InvalidArgumentException::class);

        $service->track('session-123', new Product(), 'bogus-type');
    }
}
