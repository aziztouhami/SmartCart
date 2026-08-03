<?php

namespace App\Tests\Unit\Service;

use App\Entity\Interaction;
use App\Entity\Product;
use App\Entity\User;
use App\Service\InteractionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class InteractionServiceTest extends TestCase
{
    public function testTrackPersistsInteractionWithGivenFields(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(Interaction::class));
        $em->expects($this->once())->method('flush');

        $service = new InteractionService($em);
        $user = new User();
        $product = new Product();

        $interaction = $service->track($user, $product, 'view', null);

        $this->assertSame('view', $interaction->getType());
        $this->assertSame($user, $interaction->getUser());
        $this->assertSame($product, $interaction->getProduct());
        $this->assertNull($interaction->getValue());
    }

    public function testTrackRejectsInvalidType(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $service = new InteractionService($em);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(400);

        $service->track(new User(), new Product(), 'bogus-type', null);
    }
}
