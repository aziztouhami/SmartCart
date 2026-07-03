<?php

namespace App\DTO\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class RegisterRequest
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;

    #[Assert\NotBlank]
    #[Assert\Length(min: 8)]
    public string $password;

    #[Assert\NotBlank]
    public string $firstName;

    #[Assert\NotBlank]
    public string $lastName;

    public bool $marketingOptIn = false;

    /** Optional — seeds cold-start recommendations before any real activity exists. */
    public array $preferredCategoryIds = [];

    public array $preferredBrandIds = [];
}
