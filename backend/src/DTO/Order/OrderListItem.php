<?php

namespace App\DTO\Order;

use App\Entity\Order;

class OrderListItem
{
    public int $id;
    public string $status;
    public float $totalAmount;
    public int $itemCount;
    public string $createdAt;
    public ?string $updatedAt;

    public static function fromEntity(Order $order): self
    {
        $dto = new self();
        $dto->id = $order->getId();
        $dto->status = $order->getStatus();
        $dto->totalAmount = (float) $order->getTotalAmount();
        $dto->itemCount = $order->getOrderItems()->count();
        $dto->createdAt = $order->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $dto->updatedAt = $order->getUpdatedAt()?->format(\DateTimeInterface::ATOM);

        return $dto;
    }
}
