<?php

namespace App\Tests\Functional\Admin;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Promotion;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PromotionAdminControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $adminToken;
    private Product $product;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\Promotion')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\OrderItem')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Order')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Product')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Category')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();

        $category = new Category();
        $category->setName('Cat '.uniqid());
        $category->setSlug('cat-'.uniqid());
        $this->em->persist($category);

        $this->product = new Product();
        $this->product->setName('Widget');
        $this->product->setSlug('widget-'.uniqid());
        $this->product->setPrice('100.00');
        $this->product->setStock(10);
        $this->product->setCategory($category);
        $this->em->persist($this->product);

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
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$this->adminToken, 'CONTENT_TYPE' => 'application/json'];
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

        $this->client->request('POST', '/api/admin/promotions', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'CONTENT_TYPE' => 'application/json'], content: json_encode([
            'type' => 'product',
            'productId' => $this->product->getId(),
            'discountType' => 'percentage',
            'percentage' => 10,
            'startDate' => '2026-01-01',
        ]));

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCreateProductPercentagePromotion(): void
    {
        $this->client->request('POST', '/api/admin/promotions', server: $this->headers(), content: json_encode([
            'type' => 'product',
            'productId' => $this->product->getId(),
            'discountType' => 'percentage',
            'percentage' => 10,
            'startDate' => '2026-01-01',
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('product', $data['type']);
    }

    public function testCreateFailsValidationWhenTypeMissing(): void
    {
        $this->client->request('POST', '/api/admin/promotions', server: $this->headers(), content: json_encode([
            'discountType' => 'percentage',
            'percentage' => 10,
            'startDate' => '2026-01-01',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testCreateFailsWhenFixedPriceNotLowerThanProductPrice(): void
    {
        $this->client->request('POST', '/api/admin/promotions', server: $this->headers(), content: json_encode([
            'type' => 'product',
            'productId' => $this->product->getId(),
            'discountType' => 'fixed',
            'fixedPrice' => 150,
            'startDate' => '2026-01-01',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testEndNowSetsEndDate(): void
    {
        $promotion = new Promotion();
        $promotion->setType(Promotion::TYPE_PRODUCT);
        $promotion->setProduct($this->product);
        $promotion->setDiscountType(Promotion::DISCOUNT_PERCENTAGE);
        $promotion->setPercentage('10');
        $promotion->setStartDate(new \DateTimeImmutable('-1 day'));
        $this->em->persist($promotion);
        $this->em->flush();

        $this->client->request('PATCH', '/api/admin/promotions/'.$promotion->getId().'/end', server: $this->headers());

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNotNull($data['endDate']);
    }

    public function testEndNowReturns404ForUnknownPromotion(): void
    {
        $this->client->request('PATCH', '/api/admin/promotions/999999/end', server: $this->headers());

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteRemovesPromotion(): void
    {
        $promotion = new Promotion();
        $promotion->setType(Promotion::TYPE_PRODUCT);
        $promotion->setProduct($this->product);
        $promotion->setDiscountType(Promotion::DISCOUNT_PERCENTAGE);
        $promotion->setPercentage('10');
        $promotion->setStartDate(new \DateTimeImmutable('-1 day'));
        $this->em->persist($promotion);
        $this->em->flush();
        $id = $promotion->getId();

        $this->client->request('DELETE', '/api/admin/promotions/'.$id, server: $this->headers());

        $this->assertResponseStatusCodeSame(200);
        $this->assertNull($this->em->getRepository(Promotion::class)->find($id));
    }
}
