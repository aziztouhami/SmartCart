<?php

namespace App\DTO\Product;

class UpdateStockRequest
{
    /** Set absolute stock value. */
    public ?int $quantity = null;

    /** Relative change (+/-). */
    public ?int $adjustment = null;
}
