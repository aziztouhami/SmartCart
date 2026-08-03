<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Service\AuthenticationService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;

class AuthenticationServiceTest extends TestCase
{
    public function testCreateLoginResponseBuildsTokenAndUserPayload(): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($user, 42);
        $user->setEmail('user@example.com');
        $user->setFirstName('Jane');
        $user->setLastName('Doe');
        $user->setPhone('+21612345678');
        $user->setRoles(['ROLE_USER']);

        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->expects($this->once())->method('create')->with($user)->willReturn('signed.jwt.token');

        $service = new AuthenticationService($jwtManager, 3600);

        $response = $service->createLoginResponse($user);

        $this->assertSame('signed.jwt.token', $response->token);
        $this->assertSame(3600, $response->expiresIn);
        $this->assertSame([
            'id' => 42,
            'email' => 'user@example.com',
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'phone' => '+21612345678',
            'roles' => ['ROLE_USER'],
        ], $response->user);
    }
}
