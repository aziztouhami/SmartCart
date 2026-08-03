<?php

namespace App\Tests\Functional\Admin;

use App\Entity\Category;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CategoryAdminControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\OrderItem')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Order')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Product')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Category')->execute();
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

    public function testCreateForbiddenForNonAdmin(): void
    {
        $token = $this->tokenFor('plain@example.com', ['ROLE_USER']);

        $this->client->request('POST', '/api/admin/categories', server: $this->headers($token), content: json_encode([
            'name' => 'Electronics',
        ]));

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCreateForbiddenWithoutAuthentication(): void
    {
        $this->client->request('POST', '/api/admin/categories', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'name' => 'Electronics',
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testAdminCanCreateCategory(): void
    {
        $token = $this->tokenFor('admin@example.com', ['ROLE_ADMIN']);

        $this->client->request('POST', '/api/admin/categories', server: $this->headers($token), content: json_encode([
            'name' => 'Electronics',
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Electronics', $data['name']);
    }

    public function testAdminCreateFailsWhenParentNotFound(): void
    {
        $token = $this->tokenFor('admin2@example.com', ['ROLE_ADMIN']);

        $this->client->request('POST', '/api/admin/categories', server: $this->headers($token), content: json_encode([
            'name' => 'Phones',
            'parentId' => 999999,
        ]));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testAdminCanUpdateCategory(): void
    {
        $token = $this->tokenFor('admin3@example.com', ['ROLE_ADMIN']);

        $category = new Category();
        $category->setName('Old Name');
        $category->setSlug('old-name-'.uniqid());
        $this->em->persist($category);
        $this->em->flush();

        $this->client->request('PUT', '/api/admin/categories/'.$category->getId(), server: $this->headers($token), content: json_encode([
            'name' => 'New Name',
        ]));

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('New Name', $data['name']);
    }

    public function testAdminCanDeleteCategory(): void
    {
        $token = $this->tokenFor('admin4@example.com', ['ROLE_ADMIN']);

        $category = new Category();
        $category->setName('Temp');
        $category->setSlug('temp-'.uniqid());
        $this->em->persist($category);
        $this->em->flush();
        $id = $category->getId();

        $this->client->request('DELETE', '/api/admin/categories/'.$id, server: $this->headers($token));

        $this->assertResponseStatusCodeSame(200);
        $this->assertNull($this->em->getRepository(Category::class)->find($id));
    }

    public function testDeleteReturns404ForUnknownCategory(): void
    {
        $token = $this->tokenFor('admin5@example.com', ['ROLE_ADMIN']);

        $this->client->request('DELETE', '/api/admin/categories/999999', server: $this->headers($token));

        $this->assertResponseStatusCodeSame(404);
    }
}
