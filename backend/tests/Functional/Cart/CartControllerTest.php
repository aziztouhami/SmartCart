<?php

namespace App\Tests\Functional\Cart;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CartControllerTest extends WebTestCase
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
        $this->user->setEmail('cartuser@example.com');
        $this->user->setFirstName('Cart');
        $this->user->setLastName('User');
        $this->user->setIsVerified(true);
        $this->user->setPassword($hasher->hashPassword($this->user, 'password123'));
        $this->em->persist($this->user);
        $this->em->flush();

        $this->client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'cartuser@example.com',
            'password' => 'password123',
        ]));
        $this->token = json_decode($this->client->getResponse()->getContent(), true)['token'];
    }

    private function authHeaders(): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->token, 'CONTENT_TYPE' => 'application/json'];
    }

    private function createProduct(string $name, string $price, int $stock): Product
    {
        $category = new Category();
        $category->setName('Test Category ' . uniqid());
        $category->setSlug('test-category-' . uniqid());
        $this->em->persist($category);

        $product = new Product();
        $product->setName($name);
        $product->setSlug('product-' . uniqid());
        $product->setPrice($price);
        $product->setStock($stock);
        $product->setCategory($category);
        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    public function testGetCartReturnsEmptyCartWhenNoneExists(): void
    {
        $this->client->request('GET', '/api/cart', server: $this->authHeaders());

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(0, $data['items']);
    }

    public function testGetCartRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/cart');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testAddItemAddsProductToCart(): void
    {
        $product = $this->createProduct('Widget', '15.00', 10);

        $this->client->request('POST', '/api/cart/items', server: $this->authHeaders(), content: json_encode([
            'productId' => $product->getId(),
            'quantity' => 2,
        ]));

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $data['items']);
        $this->assertSame(2, $data['items'][0]['quantity']);
    }

    public function testAddItemFailsWhenProductNotFound(): void
    {
        $this->client->request('POST', '/api/cart/items', server: $this->authHeaders(), content: json_encode([
            'productId' => 999999,
            'quantity' => 1,
        ]));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testAddItemFailsWhenQuantityExceedsStock(): void
    {
        $product = $this->createProduct('Limited', '15.00', 2);

        $this->client->request('POST', '/api/cart/items', server: $this->authHeaders(), content: json_encode([
            'productId' => $product->getId(),
            'quantity' => 5,
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testUpdateItemChangesQuantity(): void
    {
        $product = $this->createProduct('Widget', '15.00', 10);
        $cart = new Order();
        $cart->setUser($this->user);
        $cart->setStatus('cart');
        $cart->setTotalAmount('15.00');
        $item = new OrderItem();
        $item->setProduct($product);
        $item->setQuantity(1);
        $item->setPrice('15.00');
        $cart->addOrderItem($item);
        $this->em->persist($cart);
        $this->em->persist($item);
        $this->em->flush();

        $this->client->request('PUT', '/api/cart/items/' . $item->getId(), server: $this->authHeaders(), content: json_encode([
            'quantity' => 3,
        ]));

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(3, $data['items'][0]['quantity']);
    }

    public function testUpdateItemForbiddenWhenItemBelongsToAnotherUser(): void
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $otherUser = new User();
        $otherUser->setEmail('other@example.com');
        $otherUser->setFirstName('Other');
        $otherUser->setLastName('User');
        $otherUser->setIsVerified(true);
        $otherUser->setPassword($hasher->hashPassword($otherUser, 'password123'));
        $this->em->persist($otherUser);

        $product = $this->createProduct('Widget', '15.00', 10);
        $cart = new Order();
        $cart->setUser($otherUser);
        $cart->setStatus('cart');
        $cart->setTotalAmount('15.00');
        $item = new OrderItem();
        $item->setProduct($product);
        $item->setQuantity(1);
        $item->setPrice('15.00');
        $cart->addOrderItem($item);
        $this->em->persist($cart);
        $this->em->persist($item);
        $this->em->flush();

        $this->client->request('PUT', '/api/cart/items/' . $item->getId(), server: $this->authHeaders(), content: json_encode([
            'quantity' => 3,
        ]));

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRemoveItemDeletesItemAndKeepsTotalsCorrect(): void
    {
        $productA = $this->createProduct('Widget A', '10.00', 10);
        $productB = $this->createProduct('Widget B', '20.00', 10);

        $cart = new Order();
        $cart->setUser($this->user);
        $cart->setStatus('cart');
        $cart->setTotalAmount('30.00');

        $itemA = new OrderItem();
        $itemA->setProduct($productA);
        $itemA->setQuantity(1);
        $itemA->setPrice('10.00');
        $cart->addOrderItem($itemA);

        $itemB = new OrderItem();
        $itemB->setProduct($productB);
        $itemB->setQuantity(1);
        $itemB->setPrice('20.00');
        $cart->addOrderItem($itemB);

        $this->em->persist($cart);
        $this->em->persist($itemA);
        $this->em->persist($itemB);
        $this->em->flush();

        $this->client->request('DELETE', '/api/cart/items/' . $itemA->getId(), server: $this->authHeaders());

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $data['items']);
        $this->assertEquals(20, $data['total']);
    }

    public function testClearEmptiesTheCart(): void
    {
        $product = $this->createProduct('Widget', '15.00', 10);
        $cart = new Order();
        $cart->setUser($this->user);
        $cart->setStatus('cart');
        $cart->setTotalAmount('15.00');
        $item = new OrderItem();
        $item->setProduct($product);
        $item->setQuantity(1);
        $item->setPrice('15.00');
        $cart->addOrderItem($item);
        $this->em->persist($cart);
        $this->em->persist($item);
        $this->em->flush();

        $this->client->request('DELETE', '/api/cart', server: $this->authHeaders());

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(0, $data['items']);
    }
}
