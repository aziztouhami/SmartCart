<?php

namespace App\DTO\Brand;

use Symfony\Component\Validator\Constraints as Assert;

class CreateBrandRequest
{
    #[Assert\NotBlank(message: 'Brand name is required')]
    #[Assert\Length(max: 255)]
    public string $name = '';

    public ?string $image = null;

    public ?string $description = null;

    public ?string $joinedAt = null;
}
