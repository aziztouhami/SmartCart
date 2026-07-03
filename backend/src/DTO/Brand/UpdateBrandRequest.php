<?php

namespace App\DTO\Brand;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateBrandRequest
{
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    public ?string $image = null;

    public ?string $description = null;

    public ?string $joinedAt = null;
}
