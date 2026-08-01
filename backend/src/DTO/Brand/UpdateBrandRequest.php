<?php

namespace App\DTO\Brand;

class UpdateBrandRequest
{
    public ?string $name = null;

    public ?string $image = null;

    public ?string $description = null;

    public ?string $joinedAt = null;
}
