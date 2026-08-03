<?php

namespace App\Tests\Functional\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminDashboardControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    private function tokenFor(string $email, array $roles): string
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

    public function testForbiddenForNonAdmin(): void
    {
        $token = $this->tokenFor('plain@example.com', ['ROLE_USER']);

        $this->client->request('GET', '/api/admin/dashboard', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testReturnsKpiStructure(): void
    {
        $token = $this->tokenFor('admin@example.com', ['ROLE_ADMIN']);

        $this->client->request('GET', '/api/admin/dashboard', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('revenue', $data);
        $this->assertArrayHasKey('orders', $data);
        $this->assertArrayHasKey('users', $data);
        $this->assertArrayHasKey('products', $data);
        $this->assertArrayHasKey('categories', $data);
        $this->assertSame(1, $data['users']['total']);
    }

    public function testRespectsLowStockThresholdParameter(): void
    {
        $token = $this->tokenFor('admin2@example.com', ['ROLE_ADMIN']);

        $this->client->request('GET', '/api/admin/dashboard?lowStockThreshold=100', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        $this->assertResponseStatusCodeSame(200);
    }
}
