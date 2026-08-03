<?php

namespace App\Tests\Functional\Order;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class OrderControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\OrderItem')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Order')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Product')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Category')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->user = new User();
        $this->user->setEmail('orderuser@example.com');
        $this->user->setFirstName('Order');
        $this->user->setLastName('User');
        $this->user->setIsVerified(true);
        $this->user->setPassword($hasher->hashPassword($this->user, 'password123'));
        $this->em->persist($this->user);
        $this->em->flush();

        $this->client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'orderuser@example.com',
            'password' => 'password123',
        ]));
        $this->token = json_decode($this->client->getResponse()->getContent(), true)['token'];
    }

    private function authHeaders(): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'CONTENT_TYPE' => 'application/json'];
    }

    private function createProduct(string $price, int $stock): Product
    {
        $category = new Category();
        $category->setName('Test Category '.uniqid());
        $category->setSlug('test-category-'.uniqid());
        $this->em->persist($category);

        $product = new Product();
        $product->setName('Widget');
        $product->setSlug('product-'.uniqid());
        $product->setPrice($price);
        $product->setStock($stock);
        $product->setCategory($category);
        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    private function createCartWithItem(string $price, int $quantity, int $stock = 10): Order
    {
        $product = $this->createProduct($price, $stock);

        $cart = new Order();
        $cart->setUser($this->user);
        $cart->setStatus('cart');
        $cart->setTotalAmount((string) ((float) $price * $quantity));

        $item = new OrderItem();
        $item->setProduct($product);
        $item->setQuantity($quantity);
        $item->setPrice($price);
        $cart->addOrderItem($item);

        $this->em->persist($cart);
        $this->em->persist($item);
        $this->em->flush();

        return $cart;
    }

    public function testCheckoutFailsWhenCartIsEmpty(): void
    {
        $this->client->request('POST', '/api/orders/checkout', server: $this->authHeaders(), content: json_encode([
            'street' => '1 Main St',
            'city' => 'Springfield',
            'country' => 'US',
            'contactPhone' => '123456',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testCheckoutFailsWithoutContactPhone(): void
    {
        $this->createCartWithItem('10.00', 2);

        $this->client->request('POST', '/api/orders/checkout', server: $this->authHeaders(), content: json_encode([
            'street' => '1 Main St',
            'city' => 'Springfield',
            'country' => 'US',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testCheckoutTurnsCartIntoPendingOrder(): void
    {
        $this->createCartWithItem('10.00', 2);

        $this->client->request('POST', '/api/orders/checkout', server: $this->authHeaders(), content: json_encode([
            'street' => '1 Main St',
            'city' => 'Springfield',
            'country' => 'US',
            'contactPhone' => '123456',
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('pending', $data['status']);
    }

    public function testListReturnsOnlyAuthenticatedUsersOrders(): void
    {
        $cart = $this->createCartWithItem('10.00', 1);
        $cart->setStatus('pending');
        $this->em->flush();

        $this->client->request('GET', '/api/orders', server: $this->authHeaders());

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(1, $data['total']);
    }

    public function testShowReturns404ForUnknownOrder(): void
    {
        $this->client->request('GET', '/api/orders/999999', server: $this->authHeaders());

        $this->assertResponseStatusCodeSame(404);
    }

    public function testShowForbidsAccessToAnotherUsersOrder(): void
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $otherUser = new User();
        $otherUser->setEmail('otherorder@example.com');
        $otherUser->setFirstName('Other');
        $otherUser->setLastName('User');
        $otherUser->setIsVerified(true);
        $otherUser->setPassword($hasher->hashPassword($otherUser, 'password123'));
        $this->em->persist($otherUser);

        $product = $this->createProduct('10.00', 10);
        $order = new Order();
        $order->setUser($otherUser);
        $order->setStatus('pending');
        $order->setTotalAmount('10.00');
        $item = new OrderItem();
        $item->setProduct($product);
        $item->setQuantity(1);
        $item->setPrice('10.00');
        $order->addOrderItem($item);
        $this->em->persist($order);
        $this->em->persist($item);
        $this->em->flush();

        $this->client->request('GET', '/api/orders/'.$order->getId(), server: $this->authHeaders());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCancelSucceedsWhenOrderIsPending(): void
    {
        $cart = $this->createCartWithItem('10.00', 1);
        $cart->setStatus('pending');
        $this->em->flush();

        $this->client->request('POST', '/api/orders/'.$cart->getId().'/cancel', server: $this->authHeaders());

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('cancelled', $data['status']);
    }

    public function testCancelFailsWhenOrderAlreadyConfirmed(): void
    {
        $cart = $this->createCartWithItem('10.00', 1);
        $cart->setStatus('confirmed');
        $this->em->flush();

        $this->client->request('POST', '/api/orders/'.$cart->getId().'/cancel', server: $this->authHeaders());

        $this->assertResponseStatusCodeSame(400);
    }
}
