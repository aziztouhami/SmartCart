<?php

namespace App\Tests\Functional\Admin;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductType;
use App\Entity\ProductTypeAttribute;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ProductTypeAdminControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $adminToken;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\ProductTypeAttribute')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\OrderItem')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Order')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Product')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\ProductType')->execute();
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

    private function createType(string $name = 'Smartwatch'): ProductType
    {
        $type = new ProductType();
        $type->setName($name);
        $type->setSlug(strtolower($name).'-'.uniqid());
        $this->em->persist($type);
        $this->em->flush();

        return $type;
    }

    public function testListForbiddenForNonAdmin(): void
    {
        $this->client->request('GET', '/api/admin/product-types');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testListReturnsTypesWithAttributes(): void
    {
        $this->createType();

        $this->client->request('GET', '/api/admin/product-types', server: $this->headers());

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $data);
    }

    public function testCreateWithAttributesSucceeds(): void
    {
        $this->client->request('POST', '/api/admin/product-types', server: $this->headers(), content: json_encode([
            'name' => 'Smartwatch',
            'attributes' => [
                ['name' => 'Color', 'dataType' => 'text'],
                ['name' => 'Battery', 'dataType' => 'number', 'unit' => 'mAh'],
            ],
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Smartwatch', $data['name']);
        $this->assertCount(2, $data['attributes']);
    }

    public function testCreateFailsWhenNameMissing(): void
    {
        $this->client->request('POST', '/api/admin/product-types', server: $this->headers(), content: json_encode([]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testCreateFailsWhenSelectAttributeHasNoOptions(): void
    {
        $this->client->request('POST', '/api/admin/product-types', server: $this->headers(), content: json_encode([
            'name' => 'Smartwatch',
            'attributes' => [['name' => 'Strap', 'dataType' => 'select']],
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testUpdateRenamesType(): void
    {
        $type = $this->createType('Old Name');

        $this->client->request('PUT', '/api/admin/product-types/'.$type->getId(), server: $this->headers(), content: json_encode([
            'name' => 'New Name',
        ]));

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('New Name', $data['name']);
    }

    public function testUpdateReturns404ForUnknownType(): void
    {
        $this->client->request('PUT', '/api/admin/product-types/999999', server: $this->headers(), content: json_encode([
            'name' => 'New Name',
        ]));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testAddAttributeSucceeds(): void
    {
        $type = $this->createType();

        $this->client->request('POST', '/api/admin/product-types/'.$type->getId().'/attributes', server: $this->headers(), content: json_encode([
            'name' => 'Strap Material',
            'dataType' => 'text',
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $data['attributes']);
    }

    public function testAddAttributeRejectsDuplicateName(): void
    {
        $type = $this->createType();
        $attribute = new ProductTypeAttribute();
        $attribute->setName('Color');
        $attribute->setSlug('color');
        $attribute->setDataType('text');
        $type->addAttribute($attribute);
        $this->em->persist($attribute);
        $this->em->flush();

        $this->client->request('POST', '/api/admin/product-types/'.$type->getId().'/attributes', server: $this->headers(), content: json_encode([
            'name' => 'Color',
            'dataType' => 'text',
        ]));

        $this->assertResponseStatusCodeSame(409);
    }

    public function testRemoveAttributeSucceeds(): void
    {
        $type = $this->createType();
        $attribute = new ProductTypeAttribute();
        $attribute->setName('Color');
        $attribute->setSlug('color');
        $attribute->setDataType('text');
        $type->addAttribute($attribute);
        $this->em->persist($attribute);
        $this->em->flush();

        $this->client->request('DELETE', '/api/admin/product-types/'.$type->getId().'/attributes/'.$attribute->getId(), server: $this->headers());

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(0, $data['attributes']);
    }

    public function testRemoveAttributeReturns404WhenAttributeNotFound(): void
    {
        $type = $this->createType();

        $this->client->request('DELETE', '/api/admin/product-types/'.$type->getId().'/attributes/999999', server: $this->headers());

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteSucceedsWhenNoProductsAssigned(): void
    {
        $type = $this->createType();

        $this->client->request('DELETE', '/api/admin/product-types/'.$type->getId(), server: $this->headers());

        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeleteRejectedWhenProductsStillAssigned(): void
    {
        $type = $this->createType();

        $category = new Category();
        $category->setName('Cat '.uniqid());
        $category->setSlug('cat-'.uniqid());
        $this->em->persist($category);

        $product = new Product();
        $product->setName('Widget');
        $product->setSlug('widget-'.uniqid());
        $product->setPrice('10.00');
        $product->setStock(5);
        $product->setCategory($category);
        $product->setProductType($type);
        $this->em->persist($product);
        $this->em->flush();

        $this->client->request('DELETE', '/api/admin/product-types/'.$type->getId(), server: $this->headers());

        $this->assertResponseStatusCodeSame(409);
    }
}
