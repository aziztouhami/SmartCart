<?php

namespace Database\Seeders;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserSeeder extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $hasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setEmail('admin@smartcart.com');
        $admin->setFirstName('Admin');
        $admin->setLastName('SmartCart');
        $admin->setPhone(null);
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword(
            $this->hasher->hashPassword($admin, 'Admin@1234')
        );

        $manager->persist($admin);
        $manager->flush();
    }
}
