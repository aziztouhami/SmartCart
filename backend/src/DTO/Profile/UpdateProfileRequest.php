<?php

namespace App\DTO\Profile;

class UpdateProfileRequest
{
    public ?string $firstName = null;

    public ?string $lastName = null;

    public ?string $email = null;

    public ?string $phone = null;

    public ?bool $marketingOptIn = null;

    public ?array $preferredCategoryIds = null;

    public ?array $preferredBrandIds = null;
}
