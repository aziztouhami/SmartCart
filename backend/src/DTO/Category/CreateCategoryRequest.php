<?php

namespace App\DTO\Category;

class CreateCategoryRequest
{
    public string $name = '';

    public ?int $parentId = null;

    public ?string $image = null;

    /**
     * @var int[]|null
     */
    public ?array $seasonalMonths = null;
}
