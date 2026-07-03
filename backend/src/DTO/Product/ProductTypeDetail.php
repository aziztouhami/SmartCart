<?php

namespace App\DTO\Product;

use App\Entity\ProductType;
use App\Entity\ProductTypeAttribute;

class ProductTypeDetail
{
    public int $id;
    public string $name;
    public string $slug;
    public array $attributes;
    public string $createdAt;

    public static function fromEntity(ProductType $type): self
    {
        $dto = new self();
        $dto->id = $type->getId();
        $dto->name = $type->getName();
        $dto->slug = $type->getSlug();
        // array_values() keeps the PHP array's keys 0..n-1 — without it, removing
        // an attribute can leave gaps (e.g. [1 => $attr]) that json_encode renders
        // as a JSON object instead of an array, breaking frontend code that
        // expects to .map() over `attributes`.
        $dto->attributes = array_values(array_map(
            fn (ProductTypeAttribute $attr) => [
                'id'       => $attr->getId(),
                'name'     => $attr->getName(),
                'slug'     => $attr->getSlug(),
                'dataType' => $attr->getDataType(),
                'unit'     => $attr->getUnit(),
                'options'  => $attr->getOptions(),
                'required' => $attr->isRequired(),
            ],
            $type->getAttributes()->toArray()
        ));
        $dto->createdAt = $type->getCreatedAt()->format(\DateTimeInterface::ATOM);
        return $dto;
    }
}
