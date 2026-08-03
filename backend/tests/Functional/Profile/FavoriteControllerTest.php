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

class FavoriteControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Product $product;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\Favorite')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Product')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Category')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();

        $category = new Category();
        $category->setName('Electronics');
        $category->setSlug('electronics-'.uniqid());
        $this->em->persist($category);

        $this->product = new Product();
        $this->product->setName('Widget');
        $this->product->setSlug('widget-'.uniqid());
        $this->product->setPrice('10.00');
        $this->product->setStock(5);
        $this->product->setCategory($category);
        $this->em->persist($this->product);
        $this->em->flush();
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

    private function headers(string $token): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'CONTENT_TYPE' => 'application/json'];
    }

    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/profile/favorites');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testAddToFavorites(): void
    {
        [$token] = $this->tokenFor('fav1@example.com');

        $this->client->request('POST', '/api/profile/favorites', server: $this->headers($token), content: json_encode([
            'productId' => $this->product->getId(),
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame($this->product->getId(), $data['productId']);
    }

    public function testAddFailsValidationForMissingProductId(): void
    {
        [$token] = $this->tokenFor('fav2@example.com');

        $this->client->request('POST', '/api/profile/favorites', server: $this->headers($token), content: json_encode([]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testAddReturns404ForUnknownProduct(): void
    {
        [$token] = $this->tokenFor('fav3@example.com');

        $this->client->request('POST', '/api/profile/favorites', server: $this->headers($token), content: json_encode([
            'productId' => 999999,
        ]));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testAddRejectsDuplicateFavorite(): void
    {
        [$token] = $this->tokenFor('fav4@example.com');

        $this->client->request('POST', '/api/profile/favorites', server: $this->headers($token), content: json_encode([
            'productId' => $this->product->getId(),
        ]));
        $this->client->request('POST', '/api/profile/favorites', server: $this->headers($token), content: json_encode([
            'productId' => $this->product->getId(),
        ]));

        $this->assertResponseStatusCodeSame(409);
    }

    public function testListReturnsOwnFavorites(): void
    {
        [$token, $userId] = $this->tokenFor('fav5@example.com');
        $user = $this->em->getRepository(User::class)->find($userId);

        $favorite = new Favorite();
        $favorite->setUser($user);
        $favorite->setProduct($this->product);
        $this->em->persist($favorite);
        $this->em->flush();

        $this->client->request('GET', '/api/profile/favorites', server: $this->headers($token));

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(1, $data['total']);
    }

    public function testRemoveFromFavorites(): void
    {
        [$token] = $this->tokenFor('fav6@example.com');

        $this->client->request('POST', '/api/profile/favorites', server: $this->headers($token), content: json_encode([
            'productId' => $this->product->getId(),
        ]));

        $this->client->request('DELETE', '/api/profile/favorites/'.$this->product->getId(), server: $this->headers($token));

        $this->assertResponseStatusCodeSame(200);
    }

    public function testRemoveReturns404WhenNotInFavorites(): void
    {
        [$token] = $this->tokenFor('fav7@example.com');

        $this->client->request('DELETE', '/api/profile/favorites/'.$this->product->getId(), server: $this->headers($token));

        $this->assertResponseStatusCodeSame(404);
    }
}
