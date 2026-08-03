<?php

namespace App\DTO\Product;

class ProductActivity
{
    public int $viewingNow;
    public int $inCarts;

    /**
     * @param array{viewingNow: int, inCarts: int} $data
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->viewingNow = $data['viewingNow'];
        $dto->inCarts = $data['inCarts'];

        return $dto;
    }
}
