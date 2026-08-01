<?php

namespace App\DTO\Promotion;

class CreatePromotionRequest
{
    public string $type = '';

    public ?int $productId = null;

    public ?int $brandId = null;

    public string $discountType = '';

    public ?float $percentage = null;

    public ?float $fixedPrice = null;

    public ?string $startDate = null;

    public ?string $endDate = null;
}
