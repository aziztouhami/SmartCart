<?php

namespace App\DTO\Product;

use Symfony\Component\Validator\Constraints as Assert;

class CreateAttributeRequest
{
    #[Assert\NotBlank(message: 'Feature name is required')]
    #[Assert\Length(max: 255)]
    public string $name = '';

    #[Assert\NotBlank(message: 'Data type is required')]
    public string $dataType = 'text';

    public ?string $unit = null;

    /** Required, non-empty choice list when dataType is "select". */
    public ?array $options = null;

    public bool $required = false;
}
