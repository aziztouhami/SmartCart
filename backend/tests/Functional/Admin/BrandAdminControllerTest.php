<?php

namespace App\Tests\Functional\Admin;

use App\Entity\Brand;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class BrandAdminControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\Product')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Brand')->execute();
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

    private function headers(string $token): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'CONTENT_TYPE' => 'application/json'];
    }

    private function createBrand(string $name = 'Acme'): Brand
    {
        $brand = new Brand();
        $brand->setName($name);
        $brand->setJoinedAt(new \DateTimeImmutable());
        $this->em->persist($brand);
        $this->em->flush();

        return $brand;
    }

    public function testCreateForbiddenForNonAdmin(): void
    {
        $token = $this->tokenFor('plain@example.com', ['ROLE_USER']);

        $this->client->request('POST', '/api/admin/brands', server: $this->headers($token), content: json_encode([
            'name' => 'Acme',
        ]));

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminCanCreateBrand(): void
    {
        $token = $this->tokenFor('admin@example.com', ['ROLE_ADMIN']);

        $this->client->request('POST', '/api/admin/brands', server: $this->headers($token), content: json_encode([
            'name' => 'Acme',
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Acme', $data['name']);
    }

    public function testCreateFailsValidationForBlankName(): void
    {
        $token = $this->tokenFor('admin2@example.com', ['ROLE_ADMIN']);

        $this->client->request('POST', '/api/admin/brands', server: $this->headers($token), content: json_encode([
            'name' => '',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testAdminCanUpdateBrand(): void
    {
        $token = $this->tokenFor('admin3@example.com', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Old Name');

        $this->client->request('PUT', '/api/admin/brands/'.$brand->getId(), server: $this->headers($token), content: json_encode([
            'name' => 'New Name',
        ]));

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('New Name', $data['name']);
    }

    public function testUpdateReturns404ForUnknownBrand(): void
    {
        $token = $this->tokenFor('admin4@example.com', ['ROLE_ADMIN']);

        $this->client->request('PUT', '/api/admin/brands/999999', server: $this->headers($token), content: json_encode([
            'name' => 'New Name',
        ]));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testAdminCanDeleteBrand(): void
    {
        $token = $this->tokenFor('admin5@example.com', ['ROLE_ADMIN']);
        $brand = $this->createBrand('Temp');
        $id = $brand->getId();

        $this->client->request('DELETE', '/api/admin/brands/'.$id, server: $this->headers($token));

        $this->assertResponseStatusCodeSame(204);
        $this->assertNull($this->em->getRepository(Brand::class)->find($id));
    }

    public function testDeleteReturns404ForUnknownBrand(): void
    {
        $token = $this->tokenFor('admin6@example.com', ['ROLE_ADMIN']);

        $this->client->request('DELETE', '/api/admin/brands/999999', server: $this->headers($token));

        $this->assertResponseStatusCodeSame(404);
    }
}
