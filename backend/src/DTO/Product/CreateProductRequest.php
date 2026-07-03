<?php

namespace App\DTO\Product;

use Symfony\Component\Validator\Constraints as Assert;

class CreateProductRequest
{
    #[Assert\NotBlank(message: 'Product name is required')]
    #[Assert\Length(max: 255)]
    public string $name = '';

    #[Assert\NotBlank(message: 'Price is required')]
    #[Assert\Positive(message: 'Price must be a positive number')]
    public float $price = 0;

    #[Assert\NotNull(message: 'Stock is required')]
    #[Assert\PositiveOrZero(message: 'Stock cannot be negative')]
    public int $stock = 0;

    #[Assert\NotNull(message: 'Category is required')]
    #[Assert\Positive]
    public int $categoryId = 0;

    public ?string $description = null;

    public array $images = [];

    public ?int $brandId = null;

    public ?int $productTypeId = null;

    /** Feature values keyed by the type's attribute slug, e.g. {"color": "Black", "battery-capacity": 5000}. */
    public array $attributes = [];
}
