<?php

namespace App\DTO\Category;

use Symfony\Component\Validator\Constraints as Assert;

class CreateCategoryRequest
{
    #[Assert\NotBlank(message: 'Category name is required')]
    #[Assert\Length(max: 255)]
    public string $name = '';

    #[Assert\Positive]
    public ?int $parentId = null;

    public ?string $image = null;

    /**
     * @var int[]|null
     */
    #[Assert\All([new Assert\Range(min: 1, max: 12)])]
    public ?array $seasonalMonths = null;
}
