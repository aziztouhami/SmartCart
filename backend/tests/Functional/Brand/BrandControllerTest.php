<?php

namespace App\Tests\Functional\Brand;

use App\Entity\Brand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BrandControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\Brand')->execute();
    }

    private function createBrand(string $name): Brand
    {
        $brand = new Brand();
        $brand->setName($name);
        $brand->setJoinedAt(new \DateTimeImmutable());
        $this->em->persist($brand);
        $this->em->flush();

        return $brand;
    }

    public function testListReturnsPaginatedBrands(): void
    {
        $this->createBrand('Acme');
        $this->createBrand('Globex');

        $this->client->request('GET', '/api/brands');

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(2, $data['total']);
    }

    public function testShowReturnsBrandDetail(): void
    {
        $brand = $this->createBrand('Acme');

        $this->client->request('GET', '/api/brands/'.$brand->getId());

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Acme', $data['name']);
    }

    public function testShowReturns404ForUnknownBrand(): void
    {
        $this->client->request('GET', '/api/brands/999999');

        $this->assertResponseStatusCodeSame(404);
    }
}
