<?php

namespace App\DTO\Order;

use App\Entity\Order;

class AdminOrderListItem
{
    public int $id;
    public string $status;
    public float $totalAmount;
    public int $itemCount;
    public string $createdAt;
    public ?string $updatedAt;
    public int $userId;
    public string $userEmail;
    public string $userFirstName;
    public string $userLastName;

    public static function fromEntity(Order $order): self
    {
        $dto = new self();
        $dto->id          = $order->getId();
        $dto->status      = $order->getStatus();
        $dto->totalAmount = (float) $order->getTotalAmount();
        $dto->itemCount   = $order->getOrderItems()->count();
        $dto->createdAt   = $order->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $dto->updatedAt   = $order->getUpdatedAt()?->format(\DateTimeInterface::ATOM);

        $user = $order->getUser();
        $dto->userId        = $user->getId();
        $dto->userEmail     = $user->getEmail();
        $dto->userFirstName = $user->getFirstName() ?? '';
        $dto->userLastName  = $user->getLastName() ?? '';

        return $dto;
    }
}
