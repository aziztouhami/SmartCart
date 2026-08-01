<?php

namespace App\DTO\Cart;

class SyncCartRequest
{
    /**
     * Array of { productId: int, quantity: int } objects from localStorage.
     */
    public array $items = [];

    /**
     * 'merge'  → add localStorage quantities on top of existing DB cart
     * 'replace' → discard DB cart and use localStorage cart instead
     */
    public string $strategy = 'merge';
}
