<?php

namespace App\DTO\Product;

class CreateProductTypeRequest
{
    public string $name = '';

    /**
     * Optional feature definitions to create along with the type, e.g.:
     * [{"name": "Color", "dataType": "text"}, {"name": "Battery", "dataType": "number", "unit": "mAh"}]
     */
    public array $attributes = [];
}
