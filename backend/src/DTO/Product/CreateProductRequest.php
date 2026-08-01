<?php

namespace App\DTO\Product;

class CreateProductRequest
{
    public string $name = '';

    public float $price = 0;

    public int $stock = 0;

    public int $categoryId = 0;

    public ?string $description = null;

    public array $images = [];

    public ?int $brandId = null;

    public ?int $productTypeId = null;

    /** Feature values keyed by the type's attribute slug, e.g. {"color": "Black", "battery-capacity": 5000}. */
    public array $attributes = [];
}
