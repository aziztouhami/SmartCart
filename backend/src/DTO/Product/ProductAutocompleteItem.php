<?php

namespace App\DTO\Product;

use App\Entity\Product;

class ProductAutocompleteItem
{
    public int $id;
    public string $name;
    public float $price;
    public int $stock;
    public bool $inStock;
    public ?string $image;
    public ?array $category;
    public ?array $brand;

    public static function fromEntity(Product $product): self
    {
        $dto = new self();
        $dto->id = $product->getId();
        $dto->name = $product->getName();
        $dto->price = (float) $product->getPrice();
        $dto->stock = $product->getStock();
        $dto->inStock = $product->getStock() > 0;
        $dto->image = $product->getImages()[0] ?? null;
        $dto->category = $product->getCategory() ? ['name' => $product->getCategory()->getName()] : null;
        $dto->brand = $product->getBrand() ? ['name' => $product->getBrand()->getName()] : null;

        return $dto;
    }
}
