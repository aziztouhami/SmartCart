<?php

namespace App\DTO\Profile;

class ChangePasswordRequest
{
    public string $currentPassword = '';

    public string $newPassword = '';
}
