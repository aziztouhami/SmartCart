<?php

namespace App\DTO\Product;

class CreateAttributeRequest
{
    public string $name = '';

    public string $dataType = 'text';

    public ?string $unit = null;

    /** Required, non-empty choice list when dataType is "select". */
    public ?array $options = null;

    public bool $required = false;
}
