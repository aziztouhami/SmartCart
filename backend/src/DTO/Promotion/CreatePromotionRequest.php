<?php

namespace App\DTO\Promotion;

use Symfony\Component\Validator\Constraints as Assert;

class CreatePromotionRequest
{
    #[Assert\NotBlank(message: 'Promotion type is required')]
    #[Assert\Choice(choices: ['product', 'brand', 'all'], message: 'Type must be product, brand or all')]
    public string $type = '';

    public ?int $productId = null;

    public ?int $brandId = null;

    #[Assert\NotBlank(message: 'Discount type is required')]
    #[Assert\Choice(choices: ['percentage', 'fixed'], message: 'Discount type must be percentage or fixed')]
    public string $discountType = '';

    #[Assert\Range(min: 1, max: 99, notInRangeMessage: 'Percentage must be between 1 and 99')]
    public ?float $percentage = null;

    #[Assert\Positive(message: 'Fixed price must be a positive number')]
    public ?float $fixedPrice = null;

    #[Assert\NotBlank(message: 'Start date is required')]
    public ?string $startDate = null;

    public ?string $endDate = null;
}
