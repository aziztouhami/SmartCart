<?php

namespace App\Tests\Functional\Recommendation;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RecommendationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\ProductRelation')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\ColdStartRecommendation')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\OrderItem')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Order')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Product')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Category')->execute();
    }

    private function createProduct(string $name, int $stock = 5): Product
    {
        $category = new Category();
        $category->setName('Cat ' . uniqid());
        $category->setSlug('cat-' . uniqid());
        $this->em->persist($category);

        $product = new Product();
        $product->setName($name);
        $product->setSlug('product-' . uniqid());
        $product->setPrice('10.00');
        $product->setStock($stock);
        $product->setCategory($category);
        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    public function testGetReturnsEmptyListWhenCatalogHasNoColdStartData(): void
    {
        $this->client->request('GET', '/api/recommendations');

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame([], $data['recommendations']);
    }

    public function testForProductReturnsEmptySimilarAndComplementaryWithoutPrecomputedRelations(): void
    {
        $product = $this->createProduct('Widget');

        $this->client->request('GET', '/api/recommendations/product/' . $product->getId());

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame([], $data['similar']);
        $this->assertSame([], $data['complementary']);
    }

    public function testGetRespectsLimitParameter(): void
    {
        $this->client->request('GET', '/api/recommendations?limit=3');

        $this->assertResponseStatusCodeSame(200);
    }
}
