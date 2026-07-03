<?php

namespace App\Tests\Functional\Product;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProductControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Category $category;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\OrderItem')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Product')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Category')->execute();

        $this->category = new Category();
        $this->category->setName('Electronics');
        $this->category->setSlug('electronics-' . uniqid());
        $this->em->persist($this->category);
        $this->em->flush();
    }

    private function createProduct(string $name, string $price, int $stock): Product
    {
        $product = new Product();
        $product->setName($name);
        $product->setSlug('product-' . uniqid());
        $product->setPrice($price);
        $product->setStock($stock);
        $product->setCategory($this->category);
        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    public function testListReturnsPaginatedProducts(): void
    {
        $this->createProduct('Widget A', '10.00', 5);
        $this->createProduct('Widget B', '20.00', 0);

        $this->client->request('GET', '/api/products');

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(2, $data['total']);
    }

    public function testListFiltersByInStock(): void
    {
        $this->createProduct('In Stock', '10.00', 5);
        $this->createProduct('Out Of Stock', '20.00', 0);

        $this->client->request('GET', '/api/products?inStock=1');

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(1, $data['total']);
        $this->assertSame('In Stock', $data['data'][0]['name']);
    }

    public function testShowReturnsProductDetail(): void
    {
        $product = $this->createProduct('Widget', '10.00', 5);

        $this->client->request('GET', '/api/products/' . $product->getId());

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Widget', $data['name']);
    }

    public function testShowReturns404ForUnknownProduct(): void
    {
        $this->client->request('GET', '/api/products/999999');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testAutocompleteReturnsEmptyGroupsForBlankQuery(): void
    {
        $this->client->request('GET', '/api/products/autocomplete?q=');

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame([], $data['nameStart']);
    }

    public function testAutocompleteFindsProductsByNamePrefix(): void
    {
        $this->createProduct('Wireless Mouse', '15.00', 5);

        $this->client->request('GET', '/api/products/autocomplete?q=Wireless');

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNotEmpty($data['nameStart']);
    }
}
