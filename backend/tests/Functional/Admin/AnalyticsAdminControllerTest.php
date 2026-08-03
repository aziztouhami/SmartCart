<?php

namespace App\Tests\Functional\Admin;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductType;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * No real Ollama server is reached in this suite — OLLAMA_MODEL is blank by
 * default (see backend/.env.example), so every analyze call deterministically
 * takes the "AI not configured" 503 path. That's still a real, useful
 * assertion: it exercises the full stack (routing, auth, entity lookup,
 * feature-builder calls, prompt building, the client's blank-model
 * short-circuit, and RuntimeExceptionListener's error rendering).
 */
class AnalyticsAdminControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $adminToken;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setFirstName('Admin');
        $admin->setLastName('User');
        $admin->setIsVerified(true);
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($hasher->hashPassword($admin, 'password123'));
        $this->em->persist($admin);
        $this->em->flush();

        $this->client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]));
        $this->adminToken = json_decode($this->client->getResponse()->getContent(), true)['token'];
    }

    private function headers(): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$this->adminToken];
    }

    public function testAnalyzeProductForbiddenForNonAdmin(): void
    {
        $this->client->request('POST', '/api/admin/analytics/products/1/analyze');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testAnalyzeProductReturns404ForUnknownId(): void
    {
        $this->client->request('POST', '/api/admin/analytics/products/999999/analyze', server: $this->headers());

        $this->assertResponseStatusCodeSame(404);
    }

    public function testAnalyzeProductReturns503WhenAiNotConfigured(): void
    {
        $category = (new Category())->setName('Test Category')->setSlug('test-category');
        $this->em->persist($category);
        $product = (new Product())
            ->setName('Test Product')
            ->setSlug('test-product')
            ->setPrice('9.99')
            ->setStock(5)
            ->setCategory($category);
        $this->em->persist($product);
        $this->em->flush();

        $this->client->request('POST', "/api/admin/analytics/products/{$product->getId()}/analyze", server: $this->headers());

        $this->assertResponseStatusCodeSame(503);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testAnalyzeCategoryReturns404ForUnknownId(): void
    {
        $this->client->request('POST', '/api/admin/analytics/categories/999999/analyze', server: $this->headers());

        $this->assertResponseStatusCodeSame(404);
    }

    public function testAnalyzeCategoryReturns503WhenAiNotConfigured(): void
    {
        $category = (new Category())->setName('Another Category')->setSlug('another-category');
        $this->em->persist($category);
        $this->em->flush();

        $this->client->request('POST', "/api/admin/analytics/categories/{$category->getId()}/analyze", server: $this->headers());

        $this->assertResponseStatusCodeSame(503);
    }

    public function testAnalyzeBrandReturns404ForUnknownId(): void
    {
        $this->client->request('POST', '/api/admin/analytics/brands/999999/analyze', server: $this->headers());

        $this->assertResponseStatusCodeSame(404);
    }

    public function testAnalyzeBrandReturns503WhenAiNotConfigured(): void
    {
        $brand = (new Brand())->setName('Test Brand')->setJoinedAt(new \DateTimeImmutable());
        $this->em->persist($brand);
        $this->em->flush();

        $this->client->request('POST', "/api/admin/analytics/brands/{$brand->getId()}/analyze", server: $this->headers());

        $this->assertResponseStatusCodeSame(503);
    }

    public function testAnalyzeProductTypeReturns404ForUnknownId(): void
    {
        $this->client->request('POST', '/api/admin/analytics/product-types/999999/analyze', server: $this->headers());

        $this->assertResponseStatusCodeSame(404);
    }

    public function testAnalyzeProductTypeReturns503WhenAiNotConfigured(): void
    {
        $type = (new ProductType())->setName('Test Type')->setSlug('test-type');
        $this->em->persist($type);
        $this->em->flush();

        $this->client->request('POST', "/api/admin/analytics/product-types/{$type->getId()}/analyze", server: $this->headers());

        $this->assertResponseStatusCodeSame(503);
    }
}
