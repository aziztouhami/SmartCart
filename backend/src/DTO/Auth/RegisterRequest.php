<?php

namespace App\DTO\Auth;

class RegisterRequest
{
    public string $email;

    public string $password;

    public string $firstName;

    public string $lastName;

    public bool $marketingOptIn = false;

    /** Optional — seeds cold-start recommendations before any real activity exists. */
    public array $preferredCategoryIds = [];

    public array $preferredBrandIds = [];
}
