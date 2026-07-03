<?php

namespace App\DTO\Category;

use App\Entity\Category;

class CategoryTree
{
    public int $id;
    public string $name;
    public string $slug;
    public ?string $image;
    public ?array $seasonalMonths;
    public array $children;

    public static function fromEntity(Category $category): self
    {
        $dto = new self();
        $dto->id = $category->getId();
        $dto->name = $category->getName();
        $dto->slug = $category->getSlug();
        $dto->image = $category->getImage();
        $dto->seasonalMonths = $category->getSeasonalMonths();
        $dto->children = array_map(
            fn(Category $child) => self::fromEntity($child),
            $category->getChildren()->toArray()
        );
        return $dto;
    }
}
