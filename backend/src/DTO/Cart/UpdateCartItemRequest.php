<?php

namespace App\DTO\Cart;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateCartItemRequest
{
    #[Assert\NotNull]
    #[Assert\Positive]
    public int $quantity = 1;
}
