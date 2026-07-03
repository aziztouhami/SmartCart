<?php

namespace App\Tests\Functional\Admin;

use App\Entity\Category;
use App\Entity\Interaction;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminStatsControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $adminToken;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\Interaction')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\OrderItem')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Order')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Product')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Category')->execute();
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
        return ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken];
    }

    private function createProductWithInteraction(string $type): Product
    {
        $category = new Category();
        $category->setName('Cat ' . uniqid());
        $category->setSlug('cat-' . uniqid());
        $this->em->persist($category);

        $product = new Product();
        $product->setName('Widget');
        $product->setSlug('widget-' . uniqid());
        $product->setPrice('10.00');
        $product->setStock(10);
        $product->setCategory($category);
        $this->em->persist($product);

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User();
        $user->setEmail('viewer-' . uniqid() . '@example.com');
        $user->setFirstName('Viewer');
        $user->setLastName('User');
        $user->setIsVerified(true);
        $user->setPassword($hasher->hashPassword($user, 'password123'));
        $this->em->persist($user);

        $interaction = new Interaction();
        $interaction->setType($type);
        $interaction->setUser($user);
        $interaction->setProduct($product);
        $this->em->persist($interaction);

        $this->em->flush();

        return $product;
    }

    public function testBehaviorsForbiddenForNonAdmin(): void
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

        $this->client->request('GET', '/api/admin/stats/behaviors', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testBehaviorsReturnsInteractionBreakdown(): void
    {
        $this->createProductWithInteraction('view');
        $this->createProductWithInteraction('purchase');

        $this->client->request('GET', '/api/admin/stats/behaviors', server: $this->headers());

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(1, $data['overview']['views']);
        $this->assertSame(1, $data['overview']['purchases']);
        $this->assertCount(1, $data['topViewed']);
    }

    public function testProductInsightsReturns404ForUnknownProduct(): void
    {
        $this->client->request('GET', '/api/admin/stats/product/999999/insights', server: $this->headers());

        $this->assertResponseStatusCodeSame(404);
    }

    public function testProductInsightsReturnsInteractionCounts(): void
    {
        $product = $this->createProductWithInteraction('view');

        $this->client->request('GET', '/api/admin/stats/product/' . $product->getId() . '/insights', server: $this->headers());

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(1, $data['interactions']['views']);
    }
}
