<?php

namespace App\DTO\Product;

class UpdateProductRequest
{
    public ?string $name = null;

    public ?float $price = null;

    public ?int $stock = null;

    public ?int $categoryId = null;

    public ?string $description = null;

    public ?array $images = null;

    public ?int $brandId = null;

    public ?int $productTypeId = null;

    /** Feature values keyed by the type's attribute slug. Only applied when productTypeId is also supplied. */
    public ?array $attributes = null;
}
