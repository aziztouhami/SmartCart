<?php

namespace App\Tests\Functional\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserAdminControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $adminToken;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();

        $this->adminToken = $this->createUserAndLogin('admin@example.com', ['ROLE_ADMIN']);
    }

    private function createUserAndLogin(string $email, array $roles): string
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setIsVerified(true);
        $user->setRoles($roles);
        $user->setPassword($hasher->hashPassword($user, 'password123'));
        $this->em->persist($user);
        $this->em->flush();

        $this->client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email,
            'password' => 'password123',
        ]));

        return json_decode($this->client->getResponse()->getContent(), true)['token'];
    }

    private function headers(string $token): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$token];
    }

    public function testListForbiddenForNonAdmin(): void
    {
        $token = $this->createUserAndLogin('plain@example.com', ['ROLE_USER']);

        $this->client->request('GET', '/api/admin/users', server: $this->headers($token));

        $this->assertResponseStatusCodeSame(403);
    }

    public function testListReturnsAllUsers(): void
    {
        $this->client->request('GET', '/api/admin/users', server: $this->headers($this->adminToken));

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(1, $data['total']);
    }

    public function testListFiltersBySearch(): void
    {
        $this->createUserAndLogin('findme@example.com', ['ROLE_USER']);

        $this->client->request('GET', '/api/admin/users?search=findme', server: $this->headers($this->adminToken));

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(1, $data['total']);
        $this->assertSame('findme@example.com', $data['data'][0]['email']);
    }

    public function testShowReturns404ForUnknownUser(): void
    {
        $this->client->request('GET', '/api/admin/users/999999', server: $this->headers($this->adminToken));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testShowReturnsUserWithRecentOrders(): void
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $target = new User();
        $target->setEmail('target@example.com');
        $target->setFirstName('Target');
        $target->setLastName('User');
        $target->setIsVerified(true);
        $target->setPassword($hasher->hashPassword($target, 'password123'));
        $this->em->persist($target);
        $this->em->flush();

        $this->client->request('GET', '/api/admin/users/'.$target->getId(), server: $this->headers($this->adminToken));

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('target@example.com', $data['user']['email']);
        $this->assertCount(0, $data['recentOrders']);
    }

    public function testUpdateRolePromotesUserToAdmin(): void
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $target = new User();
        $target->setEmail('promote@example.com');
        $target->setFirstName('Promote');
        $target->setLastName('User');
        $target->setIsVerified(true);
        $target->setPassword($hasher->hashPassword($target, 'password123'));
        $this->em->persist($target);
        $this->em->flush();

        $this->client->request('PUT', '/api/admin/users/'.$target->getId().'/role', server: array_merge($this->headers($this->adminToken), ['CONTENT_TYPE' => 'application/json']), content: json_encode([
            'isAdmin' => true,
        ]));

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['isAdmin']);
        $this->assertContains('ROLE_ADMIN', $data['roles']);
    }

    public function testUpdateRoleReturns404ForUnknownUser(): void
    {
        $this->client->request('PUT', '/api/admin/users/999999/role', server: array_merge($this->headers($this->adminToken), ['CONTENT_TYPE' => 'application/json']), content: json_encode([
            'isAdmin' => true,
        ]));

        $this->assertResponseStatusCodeSame(404);
    }
}
