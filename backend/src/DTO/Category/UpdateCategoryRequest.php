<?php

namespace App\DTO\Category;

class UpdateCategoryRequest
{
    public ?string $name = null;

    public ?int $parentId = null;

    public ?string $image = null;

    /**
     * @var int[]|null
     */
    public ?array $seasonalMonths = null;
}
