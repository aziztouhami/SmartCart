<?php

namespace App\DTO\Cart;

use App\Entity\Order;

class CartResponse
{
    public ?int $id;
    public array $items;
    public int $itemCount;
    public float $total;

    public static function fromOrder(?Order $cart): self
    {
        $dto = new self();

        if (null === $cart) {
            $dto->id = null;
            $dto->items = [];
            $dto->itemCount = 0;
            $dto->total = 0.0;

            return $dto;
        }

        $dto->id = $cart->getId();
        $dto->items = array_map(
            fn ($item) => CartItemResponse::fromEntity($item),
            $cart->getOrderItems()->toArray()
        );
        $dto->itemCount = array_sum(array_map(fn ($i) => $i->quantity, $dto->items));
        $dto->total = round(array_sum(array_map(fn ($i) => $i->subtotal, $dto->items)), 2);

        return $dto;
    }
}
