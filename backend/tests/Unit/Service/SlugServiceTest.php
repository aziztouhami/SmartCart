<?php

namespace App\Tests\Unit\Service;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductType;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductTypeRepository;
use App\Service\SlugService;
use PHPUnit\Framework\TestCase;

class SlugServiceTest extends TestCase
{
    private ProductRepository $productRepository;
    private CategoryRepository $categoryRepository;
    private ProductTypeRepository $productTypeRepository;
    private SlugService $service;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->productTypeRepository = $this->createMock(ProductTypeRepository::class);

        $this->service = new SlugService($this->productRepository, $this->categoryRepository, $this->productTypeRepository);
    }

    public function testSlugifyLowercasesAndHyphenates(): void
    {
        $this->assertSame('wireless-mouse', $this->service->slugify('Wireless Mouse'));
    }

    public function testSlugifyCollapsesConsecutiveSpecialChars(): void
    {
        // Unicode letters (like the accented "é") are preserved as-is — this
        // slugify has no transliteration step, unlike some seeders elsewhere.
        $this->assertSame('café-au-lait', $this->service->slugify('  Café   au   lait!!  '));
    }

    public function testSlugifyTrimsLeadingAndTrailingHyphens(): void
    {
        $this->assertSame('product', $this->service->slugify('---Product---'));
    }

    public function testGenerateProductSlugReturnsBaseSlugWhenAvailable(): void
    {
        $this->productRepository->method('findBySlug')->willReturn(null);

        $this->assertSame('new-widget', $this->service->generateProductSlug('New Widget'));
    }

    public function testGenerateProductSlugAppendsCounterOnCollision(): void
    {
        $existing = $this->makeProduct(1);
        $this->productRepository->method('findBySlug')->willReturnMap([
            ['widget', $existing],
            ['widget-1', null],
        ]);

        $this->assertSame('widget-1', $this->service->generateProductSlug('Widget'));
    }

    public function testGenerateProductSlugAllowsSameSlugWhenExcludingOwnId(): void
    {
        $existing = $this->makeProduct(5);
        $this->productRepository->method('findBySlug')->willReturn($existing);

        // Editing product 5 itself — its own existing slug is not a collision.
        $this->assertSame('widget', $this->service->generateProductSlug('Widget', excludeId: 5));
    }

    public function testGenerateCategorySlugAppendsCounterOnCollision(): void
    {
        $existing = $this->makeCategory(1);
        $this->categoryRepository->method('findBySlug')->willReturnMap([
            ['electronics', $existing],
            ['electronics-1', null],
        ]);

        $this->assertSame('electronics-1', $this->service->generateCategorySlug('Electronics'));
    }

    public function testGenerateProductTypeSlugAppendsCounterOnCollision(): void
    {
        $existing = $this->makeProductType(1);
        $this->productTypeRepository->method('findBySlug')->willReturnMap([
            ['smartphone', $existing],
            ['smartphone-1', null],
        ]);

        $this->assertSame('smartphone-1', $this->service->generateProductTypeSlug('Smartphone'));
    }

    private function makeProduct(int $id): Product
    {
        $product = new Product();
        $ref = new \ReflectionProperty(Product::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($product, $id);

        return $product;
    }

    private function makeCategory(int $id): Category
    {
        $category = new Category();
        $ref = new \ReflectionProperty(Category::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($category, $id);

        return $category;
    }

    private function makeProductType(int $id): ProductType
    {
        $type = new ProductType();
        $ref = new \ReflectionProperty(ProductType::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($type, $id);

        return $type;
    }
}
