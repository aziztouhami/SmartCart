<?php

namespace App\Tests\Functional\Auth;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\User u')->execute();
    }

    private function createVerifiedUser(string $email, string $password): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setIsVerified(true);
        $user->setPassword($hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function testRegisterCreatesUnverifiedAccount(): void
    {
        $this->client->request('POST', '/api/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'firstName' => 'New',
            'lastName' => 'User',
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('newuser@example.com', $data['email']);
    }

    public function testRegisterRejectsDuplicateEmail(): void
    {
        $this->createVerifiedUser('dup@example.com', 'password123');

        $this->client->request('POST', '/api/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'dup@example.com',
            'password' => 'password123',
            'firstName' => 'Dup',
            'lastName' => 'User',
        ]));

        $this->assertResponseStatusCodeSame(409);
    }

    public function testLoginSucceedsWithValidCredentials(): void
    {
        $this->createVerifiedUser('login@example.com', 'password123');

        $this->client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'login@example.com',
            'password' => 'password123',
        ]));

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
        $this->assertSame('login@example.com', $data['user']['email']);
    }

    public function testLoginFailsWithInvalidPassword(): void
    {
        $this->createVerifiedUser('login2@example.com', 'password123');

        $this->client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'login2@example.com',
            'password' => 'wrong-password',
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLoginRejectsUnverifiedAccount(): void
    {
        $user = $this->createVerifiedUser('unverified@example.com', 'password123');
        $user->setIsVerified(false);
        $this->em->flush();

        $this->client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'unverified@example.com',
            'password' => 'password123',
        ]));

        $this->assertResponseStatusCodeSame(403);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('EMAIL_NOT_VERIFIED', $data['code']);
    }

    public function testMeRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/auth/me');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testMeReturnsCurrentUserWithValidToken(): void
    {
        $this->createVerifiedUser('me@example.com', 'password123');

        $this->client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'me@example.com',
            'password' => 'password123',
        ]));
        $token = json_decode($this->client->getResponse()->getContent(), true)['token'];

        $this->client->request('GET', '/api/auth/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('me@example.com', $data['email']);
    }
}
