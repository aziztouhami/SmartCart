<?php

namespace App\DTO\Brand;

use App\Entity\Brand;

class BrandListItem
{
    public int $id;
    public string $name;
    public ?string $image;
    public ?string $description;
    public string $joinedAt;
    public string $createdAt;
    public int $productCount;
    public int $soldCount;
    public float $revenue;
    public ?float $avgRating;

    public static function fromEntity(Brand $brand, array $stats): self
    {
        $dto = new self();
        $dto->id = $brand->getId();
        $dto->name = $brand->getName();
        $dto->image = $brand->getImage();
        $dto->description = $brand->getDescription();
        $dto->joinedAt = $brand->getJoinedAt()->format(\DateTimeInterface::ATOM);
        $dto->createdAt = $brand->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $dto->productCount = $stats['productCount'];
        $dto->soldCount = $stats['soldCount'];
        $dto->revenue = $stats['revenue'];
        $dto->avgRating = $stats['avgRating'];

        return $dto;
    }
}
