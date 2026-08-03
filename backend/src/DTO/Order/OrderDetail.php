<?php

namespace App\DTO\Order;

use App\Entity\Order;

class OrderDetail
{
    public int $id;
    public string $status;
    public float $totalAmount;
    public ?array $shippingAddress;
    public array $items;
    public string $createdAt;
    public ?string $updatedAt;

    public static function fromEntity(Order $order): self
    {
        $dto = new self();
        $dto->id = $order->getId();
        $dto->status = $order->getStatus();
        $dto->totalAmount = (float) $order->getTotalAmount();

        $rawAddress = $order->getShippingAddress();
        $dto->shippingAddress = $rawAddress
            ? (json_decode($rawAddress, true) ?? ['raw' => $rawAddress])
            : null;

        $dto->items = array_map(
            fn ($item) => OrderItemDetail::fromEntity($item),
            $order->getOrderItems()->toArray()
        );
        $dto->createdAt = $order->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $dto->updatedAt = $order->getUpdatedAt()?->format(\DateTimeInterface::ATOM);

        return $dto;
    }
}
