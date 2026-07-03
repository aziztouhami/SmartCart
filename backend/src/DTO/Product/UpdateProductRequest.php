<?php

namespace App\DTO\Product;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateProductRequest
{
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    #[Assert\Positive(message: 'Price must be a positive number')]
    public ?float $price = null;

    #[Assert\PositiveOrZero(message: 'Stock cannot be negative')]
    public ?int $stock = null;

    #[Assert\Positive]
    public ?int $categoryId = null;

    public ?string $description = null;

    public ?array $images = null;

    public ?int $brandId = null;

    public ?int $productTypeId = null;

    /** Feature values keyed by the type's attribute slug. Only applied when productTypeId is also supplied. */
    public ?array $attributes = null;
}
