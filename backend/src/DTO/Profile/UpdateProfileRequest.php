<?php

namespace App\DTO\Profile;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateProfileRequest
{
    #[Assert\Length(max: 100)]
    public ?string $firstName = null;

    #[Assert\Length(max: 100)]
    public ?string $lastName = null;

    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\Length(max: 20)]
    public ?string $phone = null;

    public ?bool $marketingOptIn = null;

    public ?array $preferredCategoryIds = null;

    public ?array $preferredBrandIds = null;
}
