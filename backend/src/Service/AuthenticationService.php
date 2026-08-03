<?php

namespace App\Service;

use App\DTO\Auth\LoginResponse;
use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class AuthenticationService
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private int $tokenTtl,
    ) {
    }

    public function createLoginResponse(User $user): LoginResponse
    {
        $token = $this->jwtManager->create($user);

        return new LoginResponse(
            token: $token,
            expiresIn: $this->tokenTtl,
            user: [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'phone' => $user->getPhone(),
                'roles' => $user->getRoles(),
            ]
        );
    }
}
