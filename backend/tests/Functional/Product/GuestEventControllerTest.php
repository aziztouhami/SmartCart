<?php

namespace App\Tests\Functional\Product;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GuestEventControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Product $product;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\GuestEvent')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\OrderItem')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Order')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Product')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Category')->execute();

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
        $this->em->flush();
    }

    public function testTrackRequiresSessionIdHeader(): void
    {
        $this->client->request('POST', '/api/guest/events', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'productId' => $this->product->getId(),
            'type' => 'view',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testTrackReturns404ForUnknownProduct(): void
    {
        $this->client->request('POST', '/api/guest/events', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SESSION_ID' => 'session-123'], content: json_encode([
            'productId' => 999999,
            'type' => 'view',
        ]));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testTrackRecordsEventForGuestSession(): void
    {
        $this->client->request('POST', '/api/guest/events', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SESSION_ID' => 'session-123'], content: json_encode([
            'productId' => $this->product->getId(),
            'type' => 'view',
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
    }

    public function testTrackRejectsInvalidType(): void
    {
        $this->client->request('POST', '/api/guest/events', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SESSION_ID' => 'session-123'], content: json_encode([
            'productId' => $this->product->getId(),
            'type' => 'bogus-type',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }
}
