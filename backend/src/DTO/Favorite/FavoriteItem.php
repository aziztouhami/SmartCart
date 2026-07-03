<?php

namespace App\DTO\Favorite;

use App\Entity\Favorite;

class FavoriteItem
{
    public int $id;
    public int $productId;
    public string $productName;
    public string $productSlug;
    public float $productPrice;
    public bool $productInStock;
    public ?string $productImage;
    public string $addedAt;

    public ?string $productCategory;
    public ?string $productBrand;

    public static function fromEntity(Favorite $favorite): self
    {
        $dto = new self();
        $dto->id             = $favorite->getId();
        $dto->productId      = $favorite->getProduct()->getId();
        $dto->productName    = $favorite->getProduct()->getName();
        $dto->productSlug    = $favorite->getProduct()->getSlug();
        $dto->productPrice   = (float) $favorite->getProduct()->getPrice();
        $dto->productInStock = $favorite->getProduct()->getStock() > 0;
        $images              = $favorite->getProduct()->getImages();
        $dto->productImage   = $images[0] ?? null;
        $dto->addedAt        = $favorite->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $dto->productCategory = $favorite->getProduct()->getCategory()?->getName();
        $dto->productBrand    = $favorite->getProduct()->getBrand()?->getName();
        return $dto;
    }
}
