<?php

namespace App\Tests\Functional\Product;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class InteractionControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $token;
    private Product $product;

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

        $category = new Category();
        $category->setName('Cat ' . uniqid());
        $category->setSlug('cat-' . uniqid());
        $this->em->persist($category);

        $this->product = new Product();
        $this->product->setName('Widget');
        $this->product->setSlug('widget-' . uniqid());
        $this->product->setPrice('10.00');
        $this->product->setStock(5);
        $this->product->setCategory($category);
        $this->em->persist($this->product);

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User();
        $user->setEmail('interactor@example.com');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setIsVerified(true);
        $user->setPassword($hasher->hashPassword($user, 'password123'));
        $this->em->persist($user);
        $this->em->flush();

        $this->client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'interactor@example.com',
            'password' => 'password123',
        ]));
        $this->token = json_decode($this->client->getResponse()->getContent(), true)['token'];
    }

    private function headers(): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->token, 'CONTENT_TYPE' => 'application/json'];
    }

    public function testTrackRequiresAuthentication(): void
    {
        $this->client->request('POST', '/api/products/' . $this->product->getId() . '/interact', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'type' => 'view',
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testTrackReturns404ForUnknownProduct(): void
    {
        $this->client->request('POST', '/api/products/999999/interact', server: $this->headers(), content: json_encode([
            'type' => 'view',
        ]));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testTrackRecordsViewInteraction(): void
    {
        $this->client->request('POST', '/api/products/' . $this->product->getId() . '/interact', server: $this->headers(), content: json_encode([
            'type' => 'view',
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('view', $data['type']);
        $this->assertSame($this->product->getId(), $data['productId']);
    }

    public function testTrackRejectsInvalidType(): void
    {
        $this->client->request('POST', '/api/products/' . $this->product->getId() . '/interact', server: $this->headers(), content: json_encode([
            'type' => 'bogus-type',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }
}
