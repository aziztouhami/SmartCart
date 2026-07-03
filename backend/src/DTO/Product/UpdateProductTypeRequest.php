<?php

namespace App\DTO\Product;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateProductTypeRequest
{
    #[Assert\NotBlank(message: 'Type name is required')]
    #[Assert\Length(max: 255)]
    public string $name = '';
}
