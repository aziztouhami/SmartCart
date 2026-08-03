<?php

namespace App\Tests\Functional\Admin;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class OrderAdminControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $adminToken;
    private User $customer;

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

        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setFirstName('Admin');
        $admin->setLastName('User');
        $admin->setIsVerified(true);
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($hasher->hashPassword($admin, 'password123'));
        $this->em->persist($admin);

        $this->customer = new User();
        $this->customer->setEmail('customer@example.com');
        $this->customer->setFirstName('Customer');
        $this->customer->setLastName('User');
        $this->customer->setIsVerified(true);
        $this->customer->setPassword($hasher->hashPassword($this->customer, 'password123'));
        $this->em->persist($this->customer);
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

    private function createOrder(string $status): Order
    {
        $category = new Category();
        $category->setName('Cat '.uniqid());
        $category->setSlug('cat-'.uniqid());
        $this->em->persist($category);

        $product = new Product();
        $product->setName('Widget');
        $product->setSlug('widget-'.uniqid());
        $product->setPrice('10.00');
        $product->setStock(10);
        $product->setCategory($category);
        $this->em->persist($product);

        $order = new Order();
        $order->setUser($this->customer);
        $order->setStatus($status);
        $order->setTotalAmount('10.00');

        $item = new OrderItem();
        $item->setProduct($product);
        $item->setQuantity(1);
        $item->setPrice('10.00');
        $order->addOrderItem($item);

        $this->em->persist($order);
        $this->em->persist($item);
        $this->em->flush();

        return $order;
    }

    public function testListForbiddenForNonAdmin(): void
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

        $this->client->request('GET', '/api/admin/orders', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testListExcludesCartOrders(): void
    {
        $this->createOrder('cart');
        $this->createOrder('pending');

        $this->client->request('GET', '/api/admin/orders', server: $this->headers());

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(1, $data['total']);
        $this->assertSame('pending', $data['data'][0]['status']);
    }

    public function testListFiltersByStatus(): void
    {
        $this->createOrder('pending');
        $this->createOrder('confirmed');

        $this->client->request('GET', '/api/admin/orders?status=confirmed', server: $this->headers());

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(1, $data['total']);
        $this->assertSame('confirmed', $data['data'][0]['status']);
    }

    public function testShowReturns404ForCartOrder(): void
    {
        $cart = $this->createOrder('cart');

        $this->client->request('GET', '/api/admin/orders/'.$cart->getId(), server: $this->headers());

        $this->assertResponseStatusCodeSame(404);
    }

    public function testShowReturnsOrderDetail(): void
    {
        $order = $this->createOrder('pending');

        $this->client->request('GET', '/api/admin/orders/'.$order->getId(), server: $this->headers());

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('pending', $data['status']);
    }

    public function testUpdateStatusAppliesValidTransition(): void
    {
        $order = $this->createOrder('pending');

        $this->client->request('PUT', '/api/admin/orders/'.$order->getId().'/status', server: $this->headers(), content: json_encode([
            'status' => 'confirmed',
        ]));

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('confirmed', $data['status']);
    }

    public function testUpdateStatusRejectsInvalidTransition(): void
    {
        $order = $this->createOrder('delivered');

        $this->client->request('PUT', '/api/admin/orders/'.$order->getId().'/status', server: $this->headers(), content: json_encode([
            'status' => 'pending',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testUpdateStatusRejectsUnknownStatusValue(): void
    {
        $order = $this->createOrder('pending');

        $this->client->request('PUT', '/api/admin/orders/'.$order->getId().'/status', server: $this->headers(), content: json_encode([
            'status' => 'not-a-real-status',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }
}
