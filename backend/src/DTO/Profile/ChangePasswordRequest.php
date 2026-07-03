<?php

namespace App\DTO\Profile;

use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordRequest
{
    #[Assert\NotBlank]
    public string $currentPassword = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 8, minMessage: 'New password must be at least 8 characters')]
    public string $newPassword = '';
}
