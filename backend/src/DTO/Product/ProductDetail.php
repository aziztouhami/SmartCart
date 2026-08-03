<?php

namespace App\DTO\Product;

use App\Entity\Product;
use App\Entity\Promotion;

class ProductDetail
{
    public int $id;
    public string $name;
    public string $slug;
    public ?string $description;
    public float $price;
    public int $stock;
    public bool $inStock;
    public array $images;
    public array $category;
    public ?array $brand;
    public ?array $productType;
    public array $attributes;
    public array $specifications;
    public ?array $promotion;
    public bool $isNew;
    public float $averageRating;
    public int $reviewCount;
    public string $createdAt;
    public ?string $updatedAt;

    public static function fromEntity(Product $product, float $avgRating = 0.0, int $reviewCount = 0, ?Promotion $promotion = null): self
    {
        $dto = new self();
        $dto->id = $product->getId();
        $dto->name = $product->getName();
        $dto->slug = $product->getSlug();
        $dto->description = $product->getDescription();
        $dto->price = (float) $product->getPrice();
        $dto->stock = $product->getStock();
        $dto->inStock = $product->getStock() > 0;
        $dto->images = $product->getImages();
        $dto->category = [
            'id' => $product->getCategory()?->getId(),
            'name' => $product->getCategory()?->getName(),
            'slug' => $product->getCategory()?->getSlug(),
        ];
        $b = $product->getBrand();
        $dto->brand = $b ? ['id' => $b->getId(), 'name' => $b->getName(), 'image' => $b->getImage()] : null;

        $type = $product->getProductType();
        $dto->productType = $type ? [
            'id' => $type->getId(),
            'name' => $type->getName(),
            'slug' => $type->getSlug(),
        ] : null;
        $dto->attributes = $product->getAttributes();

        // Ready-to-render technical sheet: each feature the type defines,
        // paired with this product's value and the unit it should display
        // with (e.g. Battery: 3349 mAh) — same unit for every product of
        // that type, since it's a property of the feature, not the product.
        $values = $product->getAttributes();
        $dto->specifications = [];
        if ($type) {
            foreach ($type->getAttributes() as $attr) {
                if (!array_key_exists($attr->getSlug(), $values)) {
                    continue;
                }
                $value = $values[$attr->getSlug()];
                $dto->specifications[] = [
                    'name' => $attr->getName(),
                    'slug' => $attr->getSlug(),
                    'value' => $value,
                    'unit' => $attr->getUnit(),
                    'dataType' => $attr->getDataType(),
                    'display' => is_bool($value)
                        ? ($value ? 'Yes' : 'No')
                        : rtrim(sprintf('%s %s', $value, $attr->getUnit() ?? '')),
                ];
            }
        }

        $dto->promotion = $promotion?->toPublicArray($dto->price);
        $dto->isNew = $product->getCreatedAt() >= new \DateTimeImmutable('-7 days');
        $dto->averageRating = round($avgRating, 1);
        $dto->reviewCount = $reviewCount;
        $dto->createdAt = $product->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $dto->updatedAt = $product->getUpdatedAt()?->format(\DateTimeInterface::ATOM);

        return $dto;
    }
}
