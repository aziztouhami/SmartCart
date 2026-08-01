<?php

namespace App\DTO\Cart;

class AddToCartRequest
{
    public int $productId = 0;

    public int $quantity = 1;
}
