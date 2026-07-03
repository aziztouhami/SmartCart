<?php

namespace App\DTO\Cart;

use Symfony\Component\Validator\Constraints as Assert;

class SyncCartRequest
{
    /**
     * Array of { productId: int, quantity: int } objects from localStorage.
     */
    #[Assert\NotNull]
    #[Assert\Type('array')]
    public array $items = [];

    /**
     * 'merge'  → add localStorage quantities on top of existing DB cart
     * 'replace' → discard DB cart and use localStorage cart instead
     */
    #[Assert\Choice(choices: ['merge', 'replace'])]
    public string $strategy = 'merge';
}
