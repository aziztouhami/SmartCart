<?php

namespace App\DTO\Admin;

use App\Entity\User;

class UserAdminItem
{
    public int $id;
    public string $email;
    public ?string $firstName;
    public ?string $lastName;
    public ?string $phone;
    public array $roles;
    public bool $isAdmin;
    public int $orderCount;
    public string $createdAt;

    public static function fromEntity(User $user, int $orderCount = 0): self
    {
        $dto             = new self();
        $dto->id         = $user->getId();
        $dto->email      = $user->getEmail();
        $dto->firstName  = $user->getFirstName();
        $dto->lastName   = $user->getLastName();
        $dto->phone      = $user->getPhone();
        $dto->roles      = $user->getRoles();
        $dto->isAdmin    = in_array('ROLE_ADMIN', $user->getRoles(), true);
        $dto->orderCount = $orderCount;
        $dto->createdAt  = $user->getCreatedAt()->format(\DateTimeInterface::ATOM);
        return $dto;
    }
}
