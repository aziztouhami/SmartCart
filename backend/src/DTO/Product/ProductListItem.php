<?php

namespace App\DTO\Product;

use App\Entity\Product;
use App\Entity\Promotion;

class ProductListItem
{
    public int $id;
    public string $name;
    public string $slug;
    public float $price;
    public int $stock;
    public bool $inStock;
    public array $images;
    public array $category;
    public ?array $brand;
    public ?array $productType;
    public array $attributes;
    public ?array $promotion;
    public bool $isNew;
    public string $createdAt;

    public static function fromEntity(Product $product, ?Promotion $promotion = null): self
    {
        $dto = new self();
        $dto->id = $product->getId();
        $dto->name = $product->getName();
        $dto->slug = $product->getSlug();
        $dto->price = (float) $product->getPrice();
        $dto->stock = $product->getStock();
        $dto->inStock = $product->getStock() > 0;
        $dto->images = $product->getImages();
        $cat    = $product->getCategory();
        $parent = $cat?->getParent();
        $dto->category = [
            'id'     => $cat?->getId(),
            'name'   => $cat?->getName(),
            'slug'   => $cat?->getSlug(),
            'parent' => $parent ? [
                'id'   => $parent->getId(),
                'name' => $parent->getName(),
                'slug' => $parent->getSlug(),
            ] : null,
        ];
        $b = $product->getBrand();
        $dto->brand = $b ? ['id' => $b->getId(), 'name' => $b->getName(), 'image' => $b->getImage()] : null;

        $type = $product->getProductType();
        $dto->productType = $type ? [
            'id'   => $type->getId(),
            'name' => $type->getName(),
            'slug' => $type->getSlug(),
        ] : null;
        $dto->attributes = $product->getAttributes();

        $dto->promotion = $promotion?->toPublicArray($dto->price);
        $dto->isNew = $product->getCreatedAt() >= new \DateTimeImmutable('-7 days');
        $dto->createdAt = $product->getCreatedAt()->format(\DateTimeInterface::ATOM);
        return $dto;
    }
}
