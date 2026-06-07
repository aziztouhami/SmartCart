<?php

namespace App\DTO\Auth;

class LoginResponse
{
    public function __construct(
        public string $token,
        public int $expiresIn,
        public array $user,
    ) {}
}
