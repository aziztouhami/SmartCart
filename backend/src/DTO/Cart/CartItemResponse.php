<?php

namespace App\DTO\Cart;

use App\Entity\OrderItem;

class CartItemResponse
{
    public int $id;
    public int $productId;
    public string $productName;
    public string $productSlug;
    public ?string $productImage;
    public float $unitPrice;
    public int $quantity;
    public float $subtotal;
    public int $availableStock;

    public static function fromEntity(OrderItem $item): self
    {
        $dto = new self();
        $dto->id = $item->getId();
        $dto->productId = $item->getProduct()->getId();
        $dto->productName = $item->getProduct()->getName();
        $dto->productSlug = $item->getProduct()->getSlug();
        $images = $item->getProduct()->getImages();
        $dto->productImage = $images[0] ?? null;
        $dto->unitPrice = (float) $item->getPrice();
        $dto->quantity = $item->getQuantity();
        $dto->subtotal = round((float) $item->getPrice() * $item->getQuantity(), 2);
        $dto->availableStock = $item->getProduct()->getStock();

        return $dto;
    }
}
