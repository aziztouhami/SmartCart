<?php

namespace App\DTO\Product;

use Symfony\Component\Validator\Constraints as Assert;

class CreateProductTypeRequest
{
    #[Assert\NotBlank(message: 'Type name is required')]
    #[Assert\Length(max: 255)]
    public string $name = '';

    /**
     * Optional feature definitions to create along with the type, e.g.:
     * [{"name": "Color", "dataType": "text"}, {"name": "Battery", "dataType": "number", "unit": "mAh"}]
     */
    public array $attributes = [];
}
