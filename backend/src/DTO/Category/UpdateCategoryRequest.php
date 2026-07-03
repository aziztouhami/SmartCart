<?php

namespace App\DTO\Category;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateCategoryRequest
{
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    #[Assert\Positive]
    public ?int $parentId = null;

    public ?string $image = null;

    /**
     * @var int[]|null
     */
    #[Assert\All([new Assert\Range(min: 1, max: 12)])]
    public ?array $seasonalMonths = null;
}
