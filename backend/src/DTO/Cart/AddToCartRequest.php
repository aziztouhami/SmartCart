<?php

namespace App\DTO\Cart;

use Symfony\Component\Validator\Constraints as Assert;

class AddToCartRequest
{
    #[Assert\NotNull]
    #[Assert\Positive]
    public int $productId = 0;

    #[Assert\NotNull]
    #[Assert\Positive]
    public int $quantity = 1;
}
