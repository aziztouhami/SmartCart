<?php

namespace App\DTO\Brand;

class CreateBrandRequest
{
    public string $name = '';

    public ?string $image = null;

    public ?string $description = null;

    public ?string $joinedAt = null;
}
