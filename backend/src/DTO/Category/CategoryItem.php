<?php

namespace App\DTO\Category;

use App\Entity\Category;

class CategoryItem
{
    public int $id;
    public string $name;
    public string $slug;
    public ?string $image;
    public ?int $parentId;
    public int $productCount;

    public static function fromEntity(Category $category, int $productCount = 0): self
    {
        $dto = new self();
        $dto->id = $category->getId();
        $dto->name = $category->getName();
        $dto->slug = $category->getSlug();
        $dto->image = $category->getImage();
        $dto->parentId = $category->getParent()?->getId();
        $dto->productCount = $productCount;
        return $dto;
    }
}
