<?php

namespace App\Service;

use App\Entity\GuestEvent;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

class GuestEventService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function track(string $sessionId, Product $product, string $type): GuestEvent
    {
        $event = new GuestEvent();
        $event->setSessionId($sessionId);
        $event->setProduct($product);

        try {
            $event->setType($type);
        } catch (\InvalidArgumentException $e) {
            throw new \RuntimeException($e->getMessage(), 400);
        }

        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }
}
