<?php

namespace App\Tests\Functional\Category;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CategoryControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\OrderItem')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Order')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Product')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Category')->execute();
    }

    public function testListReturnsOnlyRootCategoriesWithChildrenNested(): void
    {
        $root = new Category();
        $root->setName('Electronics');
        $root->setSlug('electronics-' . uniqid());
        $this->em->persist($root);

        $child = new Category();
        $child->setName('Phones');
        $child->setSlug('phones-' . uniqid());
        $root->addChild($child);
        $this->em->persist($child);
        $this->em->flush();

        $this->client->request('GET', '/api/categories');

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('Electronics', $data[0]['name']);
        $this->assertCount(1, $data[0]['children']);
        $this->assertSame('Phones', $data[0]['children'][0]['name']);
    }

    public function testShowReturns404ForUnknownCategory(): void
    {
        $this->client->request('GET', '/api/categories/999999');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testProductsEndpointReturns404ForUnknownCategory(): void
    {
        $this->client->request('GET', '/api/categories/999999/products');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testProductsEndpointListsOnlyThatCategorysProducts(): void
    {
        $categoryA = new Category();
        $categoryA->setName('Category A');
        $categoryA->setSlug('category-a-' . uniqid());
        $this->em->persist($categoryA);

        $categoryB = new Category();
        $categoryB->setName('Category B');
        $categoryB->setSlug('category-b-' . uniqid());
        $this->em->persist($categoryB);

        $productA = new Product();
        $productA->setName('Widget A');
        $productA->setSlug('widget-a-' . uniqid());
        $productA->setPrice('10.00');
        $productA->setStock(5);
        $productA->setCategory($categoryA);
        $this->em->persist($productA);

        $productB = new Product();
        $productB->setName('Widget B');
        $productB->setSlug('widget-b-' . uniqid());
        $productB->setPrice('10.00');
        $productB->setStock(5);
        $productB->setCategory($categoryB);
        $this->em->persist($productB);

        $this->em->flush();

        $this->client->request('GET', '/api/categories/' . $categoryA->getId() . '/products');

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(1, $data['products']['total']);
        $this->assertSame('Widget A', $data['products']['data'][0]['name']);
    }
}
