<?php

namespace App\Service;

use App\Entity\Interaction;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class InteractionService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function track(User $user, Product $product, string $type, ?int $value): Interaction
    {
        $interaction = new Interaction();
        $interaction->setType($type);
        $interaction->setValue($value);
        $interaction->setUser($user);
        $interaction->setProduct($product);

        $this->em->persist($interaction);
        $this->em->flush();

        return $interaction;
    }
}
