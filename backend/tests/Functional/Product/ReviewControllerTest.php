<?php

namespace App\Tests\Functional\Product;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ReviewControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $user;
    private string $token;
    private Product $product;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\Review')->execute();
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
        $this->product->setPrice('10.00');
        $this->product->setStock(5);
        $this->product->setCategory($category);
        $this->em->persist($this->product);

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->user = new User();
        $this->user->setEmail('reviewer@example.com');
        $this->user->setFirstName('Review');
        $this->user->setLastName('User');
        $this->user->setIsVerified(true);
        $this->user->setPassword($hasher->hashPassword($this->user, 'password123'));
        $this->em->persist($this->user);
        $this->em->flush();

        $this->client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'reviewer@example.com',
            'password' => 'password123',
        ]));
        $this->token = json_decode($this->client->getResponse()->getContent(), true)['token'];
    }

    private function headers(): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'CONTENT_TYPE' => 'application/json'];
    }

    private function giveUserDeliveredOrder(): void
    {
        $order = new Order();
        $order->setUser($this->user);
        $order->setStatus('delivered');
        $order->setTotalAmount('10.00');

        $item = new OrderItem();
        $item->setProduct($this->product);
        $item->setQuantity(1);
        $item->setPrice('10.00');
        $order->addOrderItem($item);

        $this->em->persist($order);
        $this->em->persist($item);
        $this->em->flush();
    }

    public function testListReturns404ForUnknownProduct(): void
    {
        $this->client->request('GET', '/api/products/999999/reviews');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testListReturnsEmptyReviewsForNewProduct(): void
    {
        $this->client->request('GET', '/api/products/'.$this->product->getId().'/reviews');

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(0, $data['reviews']['total']);
    }

    public function testCreateRequiresAuthentication(): void
    {
        $this->client->request('POST', '/api/products/'.$this->product->getId().'/reviews', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'rating' => 5,
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateRejectedWithoutDeliveredOrder(): void
    {
        $this->client->request('POST', '/api/products/'.$this->product->getId().'/reviews', server: $this->headers(), content: json_encode([
            'rating' => 5,
            'comment' => 'Great!',
        ]));

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCreateSucceedsAfterDeliveredOrder(): void
    {
        $this->giveUserDeliveredOrder();

        $this->client->request('POST', '/api/products/'.$this->product->getId().'/reviews', server: $this->headers(), content: json_encode([
            'rating' => 5,
            'comment' => 'Great!',
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(5, $data['rating']);
    }

    public function testCreateRejectsDuplicateReview(): void
    {
        $this->giveUserDeliveredOrder();

        $this->client->request('POST', '/api/products/'.$this->product->getId().'/reviews', server: $this->headers(), content: json_encode([
            'rating' => 5,
        ]));
        $this->assertResponseStatusCodeSame(201);

        $this->client->request('POST', '/api/products/'.$this->product->getId().'/reviews', server: $this->headers(), content: json_encode([
            'rating' => 4,
        ]));
        $this->assertResponseStatusCodeSame(409);
    }

    public function testCreateValidatesRatingRange(): void
    {
        $this->giveUserDeliveredOrder();

        $this->client->request('POST', '/api/products/'.$this->product->getId().'/reviews', server: $this->headers(), content: json_encode([
            'rating' => 0,
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testDeleteRejectsAnotherUsersReview(): void
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $otherUser = new User();
        $otherUser->setEmail('other@example.com');
        $otherUser->setFirstName('Other');
        $otherUser->setLastName('User');
        $otherUser->setIsVerified(true);
        $otherUser->setPassword($hasher->hashPassword($otherUser, 'password123'));
        $this->em->persist($otherUser);

        $review = new Review();
        $review->setUser($otherUser);
        $review->setProduct($this->product);
        $review->setRating(5);
        $this->em->persist($review);
        $this->em->flush();

        $this->client->request('DELETE', '/api/reviews/'.$review->getId(), server: $this->headers());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testDeleteSucceedsForOwnReview(): void
    {
        $this->giveUserDeliveredOrder();

        $this->client->request('POST', '/api/products/'.$this->product->getId().'/reviews', server: $this->headers(), content: json_encode([
            'rating' => 5,
        ]));
        $reviewId = json_decode($this->client->getResponse()->getContent(), true)['id'];

        $this->client->request('DELETE', '/api/reviews/'.$reviewId, server: $this->headers());

        $this->assertResponseStatusCodeSame(200);
    }

    public function testDeleteReturns404ForUnknownReview(): void
    {
        $this->client->request('DELETE', '/api/reviews/999999', server: $this->headers());

        $this->assertResponseStatusCodeSame(404);
    }
}
