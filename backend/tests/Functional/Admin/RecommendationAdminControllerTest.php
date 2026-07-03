<?php

namespace App\Tests\Functional\Admin;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RecommendationAdminControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $adminToken;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Recommendation\Entity\ProductRelation')->execute();
        $this->em->createQuery('DELETE FROM App\Recommendation\Entity\UserRecommendation')->execute();
        $this->em->createQuery('DELETE FROM App\Recommendation\Entity\ColdStartRecommendation')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Interaction')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\OrderItem')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Order')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Product')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Category')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();

        $category = new Category();
        $category->setName('Cat ' . uniqid());
        $category->setSlug('cat-' . uniqid());
        $this->em->persist($category);

        $product = new Product();
        $product->setName('Widget');
        $product->setSlug('widget-' . uniqid());
        $product->setPrice('10.00');
        $product->setStock(5);
        $product->setCategory($category);
        $this->em->persist($product);

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

    public function testStatusForbiddenForNonAdmin(): void
    {
        $this->client->request('GET', '/api/admin/recommendations/status');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testStatusReturnsTableRowCounts(): void
    {
        $this->client->request('GET', '/api/admin/recommendations/status', server: $this->headers());

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(0, $data['relationRows']);
        $this->assertSame(0, $data['userRecommendationRows']);
    }

    public function testRebuildRunsAllThreeBuildersWithoutError(): void
    {
        $this->client->request('POST', '/api/admin/recommendations/rebuild', server: $this->headers());

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('coldStart', $data);
        $this->assertArrayHasKey('itemRelations', $data);
        $this->assertArrayHasKey('userRecommendations', $data);
    }
}
