<?php

namespace App\Tests\Unit\Service;

use App\DTO\Product\CreateProductRequest;
use App\DTO\Product\UpdateProductRequest;
use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Product;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductTypeRepository;
use App\Service\ProductService;
use App\Service\ProductTypeService;
use App\Service\SlugService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ProductServiceTest extends TestCase
{
    private CategoryRepository $categoryRepository;
    private BrandRepository $brandRepository;
    private ProductTypeRepository $productTypeRepository;
    private EntityManagerInterface $em;
    private SlugService $slugService;
    private ProductService $service;

    protected function setUp(): void
    {
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->brandRepository = $this->createMock(BrandRepository::class);
        $this->productTypeRepository = $this->createMock(ProductTypeRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->slugService = $this->createMock(SlugService::class);
        $this->slugService->method('generateProductSlug')->willReturn('a-slug');

        $this->service = new ProductService(
            $this->categoryRepository,
            $this->brandRepository,
            $this->productTypeRepository,
            $this->createMock(ProductTypeService::class),
            $this->em,
            $this->slugService,
        );
    }

    private function leafCategory(): Category
    {
        return new Category();
    }

    public function testCreateThrowsWhenCategoryNotFound(): void
    {
        $this->categoryRepository->method('find')->willReturn(null);

        $dto = new CreateProductRequest();
        $dto->name = 'Widget';
        $dto->price = 9.99;
        $dto->stock = 5;
        $dto->categoryId = 1;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Category not found');

        $this->service->create($dto);
    }

    public function testCreateThrowsWhenCategoryHasChildren(): void
    {
        $parent = new Category();
        $parent->addChild(new Category());
        $this->categoryRepository->method('find')->willReturn($parent);

        $dto = new CreateProductRequest();
        $dto->name = 'Widget';
        $dto->price = 9.99;
        $dto->stock = 5;
        $dto->categoryId = 1;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Products can only be assigned to sub-categories, not parent categories.');

        $this->service->create($dto);
    }

    public function testCreateThrowsWhenBrandNotFound(): void
    {
        $this->categoryRepository->method('find')->willReturn($this->leafCategory());
        $this->brandRepository->method('find')->willReturn(null);

        $dto = new CreateProductRequest();
        $dto->name = 'Widget';
        $dto->price = 9.99;
        $dto->stock = 5;
        $dto->categoryId = 1;
        $dto->brandId = 99;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Brand not found');

        $this->service->create($dto);
    }

    public function testCreatePersistsProductWithGeneratedSlug(): void
    {
        $this->categoryRepository->method('find')->willReturn($this->leafCategory());
        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(Product::class));
        $this->em->expects($this->once())->method('flush');

        $dto = new CreateProductRequest();
        $dto->name = 'Widget';
        $dto->price = 9.99;
        $dto->stock = 5;
        $dto->categoryId = 1;

        $product = $this->service->create($dto);

        $this->assertSame('Widget', $product->getName());
        $this->assertSame('9.99', $product->getPrice());
        $this->assertSame(5, $product->getStock());
        $this->assertSame('a-slug', $product->getSlug());
    }

    public function testCreateAttachesBrandWhenProvided(): void
    {
        $brand = new Brand();
        $this->categoryRepository->method('find')->willReturn($this->leafCategory());
        $this->brandRepository->method('find')->willReturn($brand);

        $dto = new CreateProductRequest();
        $dto->name = 'Widget';
        $dto->price = 9.99;
        $dto->stock = 5;
        $dto->categoryId = 1;
        $dto->brandId = 1;

        $product = $this->service->create($dto);

        $this->assertSame($brand, $product->getBrand());
    }

    public function testUpdateThrowsWhenNewCategoryHasChildren(): void
    {
        $product = new Product();
        $product->setName('Old');
        $product->setSlug('old');
        $product->setPrice('1.00');
        $product->setStock(1);
        $product->setCategory($this->leafCategory());

        $parentWithChildren = new Category();
        $parentWithChildren->addChild(new Category());
        $this->categoryRepository->method('find')->willReturn($parentWithChildren);

        $dto = new UpdateProductRequest();
        $dto->categoryId = 2;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Products can only be assigned to sub-categories, not parent categories.');

        $this->service->update($product, $dto);
    }

    public function testUpdateOnlyChangesSuppliedFields(): void
    {
        $product = new Product();
        $product->setName('Old');
        $product->setSlug('old');
        $product->setPrice('1.00');
        $product->setStock(1);
        $product->setCategory($this->leafCategory());

        $dto = new UpdateProductRequest();
        $dto->price = 5.50;

        $result = $this->service->update($product, $dto);

        $this->assertSame('Old', $result->getName());
        $this->assertSame('5.5', $result->getPrice());
        $this->assertSame(1, $result->getStock());
    }

    public function testUpdateStockSetsAbsoluteQuantity(): void
    {
        $product = new Product();
        $product->setStock(5);

        $result = $this->service->updateStock($product, ['quantity' => 20]);

        $this->assertSame(20, $result->getStock());
    }

    public function testUpdateStockRejectsNegativeQuantity(): void
    {
        $product = new Product();
        $product->setStock(5);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stock cannot be negative');

        $this->service->updateStock($product, ['quantity' => -1]);
    }

    public function testUpdateStockAppliesPositiveAdjustment(): void
    {
        $product = new Product();
        $product->setStock(5);

        $result = $this->service->updateStock($product, ['adjustment' => 3]);

        $this->assertSame(8, $result->getStock());
    }

    public function testUpdateStockRejectsAdjustmentResultingInNegativeStock(): void
    {
        $product = new Product();
        $product->setStock(5);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Adjustment would result in negative stock');

        $this->service->updateStock($product, ['adjustment' => -10]);
    }

    public function testUpdateStockThrowsWhenNeitherQuantityNorAdjustmentGiven(): void
    {
        $product = new Product();
        $product->setStock(5);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Provide either "quantity" or "adjustment"');

        $this->service->updateStock($product, []);
    }

    public function testDeleteRemovesProduct(): void
    {
        $product = new Product();

        $this->em->expects($this->once())->method('remove')->with($product);
        $this->em->expects($this->once())->method('flush');

        $this->service->delete($product);
    }
}
