<?php

namespace App\DTO\Order;

use App\Entity\OrderItem;

class OrderItemDetail
{
    public int $id;
    public int $productId;
    public string $productName;
    public string $productSlug;
    public float $unitPrice;
    public int $quantity;
    public float $subtotal;

    public static function fromEntity(OrderItem $item): self
    {
        $dto = new self();
        $dto->id = $item->getId();
        $dto->productId = $item->getProduct()->getId();
        $dto->productName = $item->getProduct()->getName();
        $dto->productSlug = $item->getProduct()->getSlug();
        $dto->unitPrice = (float) $item->getPrice();
        $dto->quantity = $item->getQuantity();
        $dto->subtotal = round((float) $item->getPrice() * $item->getQuantity(), 2);

        return $dto;
    }
}
