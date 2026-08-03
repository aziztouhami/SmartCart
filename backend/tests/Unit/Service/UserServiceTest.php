<?php

namespace App\Tests\Unit\Service;

use App\DTO\Auth\RegisterRequest;
use App\Entity\User;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use App\Service\MailService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserServiceTest extends TestCase
{
    private UserRepository $userRepository;
    private CategoryRepository $categoryRepository;
    private BrandRepository $brandRepository;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;
    private MailService $mailService;
    private UserService $service;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->brandRepository = $this->createMock(BrandRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->mailService = $this->createMock(MailService::class);
        // Mirrors MailService::sendSafely()'s real try/catch — without this, the
        // mock's sendSafely() is a no-op and the callback (the actual
        // sendXxxEmail assertion target) never runs.
        $this->mailService->method('sendSafely')->willReturnCallback(function (callable $send) {
            try {
                $send();
            } catch (\Throwable) {
            }
        });

        $this->service = new UserService(
            $this->userRepository,
            $this->categoryRepository,
            $this->brandRepository,
            $this->em,
            $this->passwordHasher,
            $this->mailService,
        );
    }

    public function testRegisterThrowsWhenEmailAlreadyInUse(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(new User());

        $dto = new RegisterRequest();
        $dto->email = 'taken@example.com';
        $dto->password = 'password123';
        $dto->firstName = 'John';
        $dto->lastName = 'Doe';

        $this->em->expects($this->never())->method('persist');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Email already in use');

        $this->service->register($dto);
    }

    public function testRegisterCreatesUnverifiedUserAndSendsEmail(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed-password');

        $dto = new RegisterRequest();
        $dto->email = 'new@example.com';
        $dto->password = 'password123';
        $dto->firstName = 'Jane';
        $dto->lastName = 'Smith';

        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(User::class));
        $this->em->expects($this->once())->method('flush');
        $this->mailService->expects($this->once())->method('sendVerificationEmail');

        $user = $this->service->register($dto);

        $this->assertSame('new@example.com', $user->getEmail());
        $this->assertSame('hashed-password', $user->getPassword());
        $this->assertFalse($user->isVerified());
        $this->assertNotNull($user->getVerificationToken());
    }

    public function testRegisterSucceedsEvenWhenMailDeliveryFails(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed-password');
        $this->mailService->method('sendVerificationEmail')->willThrowException(new \RuntimeException('SMTP down'));

        $dto = new RegisterRequest();
        $dto->email = 'new@example.com';
        $dto->password = 'password123';
        $dto->firstName = 'Jane';
        $dto->lastName = 'Smith';

        $user = $this->service->register($dto);

        $this->assertSame('new@example.com', $user->getEmail());
    }

    public function testVerifyCredentialsThrowsWhenUserNotFound(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid email or password');

        $this->service->verifyCredentials('missing@example.com', 'password123');
    }

    public function testVerifyCredentialsThrowsWhenPasswordInvalid(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->passwordHasher->method('isPasswordValid')->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid email or password');

        $this->service->verifyCredentials('user@example.com', 'wrong-password');
    }

    public function testVerifyCredentialsReturnsUserWhenValid(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);

        $result = $this->service->verifyCredentials('user@example.com', 'correct-password');

        $this->assertSame($user, $result);
    }
}
