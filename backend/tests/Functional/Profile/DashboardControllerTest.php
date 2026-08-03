<?php

namespace App\Tests\Functional\Profile;

use App\Entity\Category;
use App\Entity\Favorite;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DashboardControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\Favorite')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\OrderItem')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Order')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Product')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Category')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    private function tokenFor(string $email): array
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setIsVerified(true);
        $user->setPassword($hasher->hashPassword($user, 'password123'));
        $this->em->persist($user);
        $this->em->flush();

        $this->client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email,
            'password' => 'password123',
        ]));

        $token = json_decode($this->client->getResponse()->getContent(), true)['token'];

        return [$token, $user->getId()];
    }

    public function testRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/profile/dashboard');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testReturnsOrdersAndFavoritesSummary(): void
    {
        [$token, $userId] = $this->tokenFor('dash1@example.com');
        $user = $this->em->getRepository(User::class)->find($userId);

        $category = new Category();
        $category->setName('Electronics');
        $category->setSlug('electronics-'.uniqid());
        $this->em->persist($category);

        $product = new Product();
        $product->setName('Widget');
        $product->setSlug('widget-'.uniqid());
        $product->setPrice('10.00');
        $product->setStock(5);
        $product->setCategory($category);
        $this->em->persist($product);

        $favorite = new Favorite();
        $favorite->setUser($user);
        $favorite->setProduct($product);
        $this->em->persist($favorite);
        $this->em->flush();

        $this->client->request('GET', '/api/profile/dashboard', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(0, $data['orders']['total']);
        $this->assertSame(1, $data['favorites']['total']);
        $this->assertCount(1, $data['favorites']['recent']);
    }
}
