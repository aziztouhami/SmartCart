<?php

namespace App\Service;

use App\Domain\Exception\ApiException;
use App\DTO\Auth\RegisterRequest;
use App\DTO\Profile\ChangePasswordRequest;
use App\DTO\Profile\UpdateProfileRequest;
use App\Entity\User;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    private const DELETION_GRACE_PERIOD_DAYS = 30;

    public function __construct(
        private UserRepository $userRepository,
        private CategoryRepository $categoryRepository,
        private BrandRepository $brandRepository,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private MailService $mailService,
    ) {
    }

    public function register(RegisterRequest $dto): User
    {
        if ($this->userRepository->findOneBy(['email' => $dto->email])) {
            throw new \RuntimeException('Email already in use', 409);
        }

        $user = new User();
        $user->setEmail($dto->email);
        $user->setFirstName($dto->firstName);
        $user->setLastName($dto->lastName);
        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));
        $user->setRoles(['ROLE_USER']);
        $user->setMarketingOptIn($dto->marketingOptIn);
        $user->setPreferredCategoryIds($this->validCategoryIds($dto->preferredCategoryIds));
        $user->setPreferredBrandIds($this->validBrandIds($dto->preferredBrandIds));
        $user->setIsVerified(false);
        $user->setVerificationToken(bin2hex(random_bytes(32)));

        $this->em->persist($user);
        $this->em->flush();

        $this->mailService->sendSafely(fn () => $this->mailService->sendVerificationEmail($user));

        return $user;
    }

    public function resendVerificationEmail(string $email): void
    {
        $user = $this->userRepository->findByEmail($email);
        if (!$user || $user->isVerified()) {
            return;
        }

        $user->setVerificationToken(bin2hex(random_bytes(32)));
        $this->em->flush();

        $this->mailService->sendSafely(fn () => $this->mailService->sendVerificationEmail($user));
    }

    public function verifyEmail(string $token): User
    {
        $user = $this->userRepository->findByVerificationToken($token);
        if (!$user) {
            throw new \RuntimeException('Invalid or expired confirmation link', 400);
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $this->em->flush();

        return $user;
    }

    public function verifyCredentials(string $email, string $password): User
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            throw new \RuntimeException('Invalid email or password', 401);
        }

        $valid = $this->passwordHasher->isPasswordValid($user, $password);

        if (!$valid) {
            throw new \RuntimeException('Invalid email or password', 401);
        }

        if (null !== $user->getDeletionRequestedAt()) {
            $cutoff = $user->getDeletionRequestedAt()->modify('+'.self::DELETION_GRACE_PERIOD_DAYS.' days');
            if (new \DateTimeImmutable() > $cutoff) {
                $this->em->remove($user);
                $this->em->flush();
                throw new \RuntimeException('Invalid email or password', 401);
            }

            $user->setDeletionRequestedAt(null);
            $this->em->flush();
        }

        if (!$user->isVerified()) {
            throw new ApiException('Please confirm your email address before logging in.', 403, 'EMAIL_NOT_VERIFIED');
        }

        return $user;
    }

    public function requestDeletion(User $user): void
    {
        $user->setDeletionRequestedAt(new \DateTimeImmutable());
        $this->em->flush();
    }

    /**
     * Permanently removes accounts whose deletion grace period has elapsed.
     * Intended to be run on a schedule; verifyCredentials() also purges
     * lazily on next login attempt as a safety net.
     */
    public function purgeExpiredDeletions(): int
    {
        $cutoff = (new \DateTimeImmutable())->modify('-'.self::DELETION_GRACE_PERIOD_DAYS.' days');
        $users = $this->userRepository->findScheduledForDeletionBefore($cutoff);

        foreach ($users as $user) {
            $this->em->remove($user);
        }
        $this->em->flush();

        return count($users);
    }

    public function updateProfile(User $user, UpdateProfileRequest $dto): User
    {
        if (null !== $dto->email && $dto->email !== $user->getEmail()) {
            if ($this->userRepository->findOneBy(['email' => $dto->email])) {
                throw new \RuntimeException('Email already in use', 409);
            }
            $user->setEmail($dto->email);
        }
        if (null !== $dto->firstName) {
            $user->setFirstName($dto->firstName);
        }
        if (null !== $dto->lastName) {
            $user->setLastName($dto->lastName);
        }
        if (null !== $dto->phone) {
            $user->setPhone($dto->phone);
        }
        if (null !== $dto->marketingOptIn) {
            $user->setMarketingOptIn($dto->marketingOptIn);
        }
        if (null !== $dto->preferredCategoryIds) {
            $user->setPreferredCategoryIds($this->validCategoryIds($dto->preferredCategoryIds));
        }
        if (null !== $dto->preferredBrandIds) {
            $user->setPreferredBrandIds($this->validBrandIds($dto->preferredBrandIds));
        }

        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $user;
    }

    /**
     * @return int[]
     */
    private function validCategoryIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $found = $this->categoryRepository->findBy(['id' => array_map('intval', $ids)]);

        return array_map(fn ($c) => $c->getId(), $found);
    }

    /**
     * @return int[]
     */
    private function validBrandIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $found = $this->brandRepository->findBy(['id' => array_map('intval', $ids)]);

        return array_map(fn ($b) => $b->getId(), $found);
    }

    public function changePassword(User $user, ChangePasswordRequest $dto): void
    {
        if (!$this->passwordHasher->isPasswordValid($user, $dto->currentPassword)) {
            throw new \RuntimeException('Current password is incorrect', 400);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->newPassword));
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
    }

    public function findOrCreateFromGoogle(array $profile): User
    {
        $email = $profile['email'] ?? '';
        if (!$email) {
            throw new \RuntimeException('No email returned from Google', 400);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if ($user) {
            return $user;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($profile['given_name'] ?? '');
        $user->setLastName($profile['family_name'] ?? '');
        // Google users have no password; set a random hash so the field is non-null
        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(16))));
        $user->setRoles(['ROLE_USER']);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function updateRole(User $user, bool $isAdmin): User
    {
        $roles = ['ROLE_USER'];
        if ($isAdmin) {
            $roles[] = 'ROLE_ADMIN';
        }
        $user->setRoles($roles);
        $this->em->flush();

        return $user;
    }
}
