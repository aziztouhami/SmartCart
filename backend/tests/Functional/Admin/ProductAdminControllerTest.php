<?php

namespace App\Tests\Functional\Admin;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ProductAdminControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Category $category;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\OrderItem')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Order')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Product')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Category')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();

        $this->category = new Category();
        $this->category->setName('Electronics');
        $this->category->setSlug('electronics-'.uniqid());
        $this->em->persist($this->category);
        $this->em->flush();
    }

    private function adminToken(): string
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $admin = new User();
        $admin->setEmail('admin-'.uniqid().'@example.com');
        $admin->setFirstName('Admin');
        $admin->setLastName('User');
        $admin->setIsVerified(true);
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($hasher->hashPassword($admin, 'password123'));
        $this->em->persist($admin);
        $this->em->flush();

        $this->client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $admin->getEmail(),
            'password' => 'password123',
        ]));

        return json_decode($this->client->getResponse()->getContent(), true)['token'];
    }

    private function headers(string $token): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'CONTENT_TYPE' => 'application/json'];
    }

    private function createProduct(string $name = 'Widget', int $stock = 10): Product
    {
        $product = new Product();
        $product->setName($name);
        $product->setSlug('widget-'.uniqid());
        $product->setPrice('10.00');
        $product->setStock($stock);
        $product->setCategory($this->category);
        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    public function testCreateForbiddenForNonAdmin(): void
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $plain = new User();
        $plain->setEmail('plain@example.com');
        $plain->setFirstName('Plain');
        $plain->setLastName('User');
        $plain->setIsVerified(true);
        $plain->setPassword($hasher->hashPassword($plain, 'password123'));
        $this->em->persist($plain);
        $this->em->flush();

        $this->client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'plain@example.com',
            'password' => 'password123',
        ]));
        $token = json_decode($this->client->getResponse()->getContent(), true)['token'];

        $this->client->request('POST', '/api/admin/products', server: $this->headers($token), content: json_encode([
            'name' => 'New Product',
            'price' => 9.99,
            'stock' => 5,
            'categoryId' => $this->category->getId(),
        ]));

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminCanCreateProduct(): void
    {
        $token = $this->adminToken();

        $this->client->request('POST', '/api/admin/products', server: $this->headers($token), content: json_encode([
            'name' => 'New Product',
            'price' => 9.99,
            'stock' => 5,
            'categoryId' => $this->category->getId(),
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('New Product', $data['name']);
    }

    public function testCreateFailsValidationForMissingName(): void
    {
        $token = $this->adminToken();

        $this->client->request('POST', '/api/admin/products', server: $this->headers($token), content: json_encode([
            'price' => 9.99,
            'stock' => 5,
            'categoryId' => $this->category->getId(),
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testAdminCanUpdateProduct(): void
    {
        $token = $this->adminToken();
        $product = $this->createProduct();

        $this->client->request('PUT', '/api/admin/products/'.$product->getId(), server: $this->headers($token), content: json_encode([
            'name' => 'Renamed',
        ]));

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Renamed', $data['name']);
    }

    public function testUpdateReturns404ForUnknownProduct(): void
    {
        $token = $this->adminToken();

        $this->client->request('PUT', '/api/admin/products/999999', server: $this->headers($token), content: json_encode([
            'name' => 'Renamed',
        ]));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testUpdateStockSetsAbsoluteQuantity(): void
    {
        $token = $this->adminToken();
        $product = $this->createProduct(stock: 10);

        $this->client->request('PATCH', '/api/admin/products/'.$product->getId().'/stock', server: $this->headers($token), content: json_encode([
            'quantity' => 42,
        ]));

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(42, $data['stock']);
    }

    public function testUpdateStockRejectsNegativeQuantity(): void
    {
        $token = $this->adminToken();
        $product = $this->createProduct(stock: 10);

        $this->client->request('PATCH', '/api/admin/products/'.$product->getId().'/stock', server: $this->headers($token), content: json_encode([
            'quantity' => -1,
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testAdminCanDeleteProduct(): void
    {
        $token = $this->adminToken();
        $product = $this->createProduct();
        $id = $product->getId();

        $this->client->request('DELETE', '/api/admin/products/'.$id, server: $this->headers($token));

        $this->assertResponseStatusCodeSame(200);
        $this->assertNull($this->em->getRepository(Product::class)->find($id));
    }

    public function testDeleteReturns404ForUnknownProduct(): void
    {
        $token = $this->adminToken();

        $this->client->request('DELETE', '/api/admin/products/999999', server: $this->headers($token));

        $this->assertResponseStatusCodeSame(404);
    }
}
