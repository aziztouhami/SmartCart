<?php

namespace App\Tests\Unit\Service\Feature;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\Feature\InteractionAggregationService;
use App\Service\Feature\ProductFeatureBuilder;
use PHPUnit\Framework\TestCase;

class ProductFeatureBuilderTest extends TestCase
{
    private ProductRepository $productRepository;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepository::class);
    }

    private function makeProduct(int $id, string $name, string $price, int $stock, ?\DateTimeImmutable $createdAt = null): Product
    {
        $product = new Product();
        $ref = new \ReflectionProperty(Product::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($product, $id);
        $product->setName($name);
        $product->setPrice($price);
        $product->setStock($stock);
        $product->setCreatedAt($createdAt ?? new \DateTimeImmutable());

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

    private function makeBrand(int $id): Brand
    {
        $brand = new Brand();
        $ref = new \ReflectionProperty(Brand::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($brand, $id);

        return $brand;
    }

    /**
     * @param array<string, array<int, array{count?: int, qty?: int}>> $countsByType keyed by interaction type ('view'|'cart'|'purchase')
     * @param array<int, \DateTimeImmutable>                           $lastSeen
     */
    private function makeAggregation(
        array $countsByType = [],
        array $favorites = [],
        array $reviews = [],
        array $lastSeen = [],
    ): InteractionAggregationService {
        $agg = $this->createMock(InteractionAggregationService::class);
        $agg->method('countsByType')->willReturnCallback(
            static fn (string $type, string $groupBy, bool $joinProduct = false) => $countsByType[$type] ?? []
        );
        $agg->method('favoritesGroupedBy')->willReturn($favorites);
        $agg->method('reviewsGroupedBy')->willReturn($reviews);
        $agg->method('lastInteractionGroupedBy')->willReturn($lastSeen);

        return $agg;
    }

    public function testBuildReturnsEmptyArrayWhenNoProducts(): void
    {
        $this->productRepository->method('findAll')->willReturn([]);
        $builder = new ProductFeatureBuilder($this->productRepository, $this->makeAggregation());

        $this->assertSame([], $builder->build());
    }

    public function testBuildComputesFullRowWithData(): void
    {
        $category = $this->makeCategory(10);
        $brand = $this->makeBrand(20);
        $product = $this->makeProduct(1, 'Widget', '19.99', 42, new \DateTimeImmutable('-10 days'));
        $product->setCategory($category);
        $product->setBrand($brand);
        $this->productRepository->method('findAll')->willReturn([$product]);

        $lastSeenAt = new \DateTimeImmutable('2024-05-01T12:00:00+00:00');
        $aggregation = $this->makeAggregation(
            countsByType: [
                'view' => [1 => ['count' => 100, 'qty' => 0]],
                'cart' => [1 => ['count' => 25, 'qty' => 30]],
                'purchase' => [1 => ['count' => 5, 'qty' => 6]],
            ],
            favorites: [1 => 3],
            reviews: [1 => ['count' => 2, 'avg' => 4.555]],
            lastSeen: [1 => $lastSeenAt],
        );

        $builder = new ProductFeatureBuilder($this->productRepository, $aggregation);
        $rows = $builder->build();

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame(1, $row['productId']);
        $this->assertSame('Widget', $row['name']);
        $this->assertSame(10, $row['categoryId']);
        $this->assertSame(20, $row['brandId']);
        $this->assertSame(19.99, $row['price']);
        $this->assertIsFloat($row['price']);
        $this->assertSame(42, $row['stock']);
        $this->assertSame(10, $row['ageDays']);
        $this->assertSame(100, $row['views']);
        $this->assertSame(25, $row['cartAdds']);
        $this->assertSame(30, $row['cartQuantity']);
        $this->assertSame(5, $row['purchases']);
        $this->assertSame(6, $row['purchaseQuantity']);
        $this->assertSame(3, $row['favorites']);
        $this->assertSame(2, $row['reviewCount']);
        $this->assertSame(4.56, $row['avgRating']);
        $this->assertSame(0.25, $row['viewToCartRate']); // 25 / 100
        $this->assertSame(0.2, $row['cartToPurchaseRate']); // 5 / 25
        $this->assertSame(0.05, $row['conversionRate']); // 5 / 100
        $this->assertSame($lastSeenAt->format('c'), $row['lastInteractionAt']);
    }

    public function testBuildHandlesNullCategoryAndBrand(): void
    {
        $product = $this->makeProduct(2, 'Orphan', '5.00', 1);
        // No category/brand set — Product allows null in the entity even
        // though the DB column is non-nullable for category.
        $this->productRepository->method('findAll')->willReturn([$product]);

        $builder = new ProductFeatureBuilder($this->productRepository, $this->makeAggregation());
        $rows = $builder->build();

        $this->assertNull($rows[0]['categoryId']);
        $this->assertNull($rows[0]['brandId']);
    }

    public function testBuildGuardsAllRatesAgainstDivisionByZero(): void
    {
        $product = $this->makeProduct(3, 'NoActivity', '5.00', 1);
        $this->productRepository->method('findAll')->willReturn([$product]);

        $builder = new ProductFeatureBuilder($this->productRepository, $this->makeAggregation());
        $rows = $builder->build();

        $row = $rows[0];
        $this->assertSame(0, $row['views']);
        $this->assertSame(0, $row['cartAdds']);
        $this->assertSame(0, $row['purchases']);
        $this->assertSame(0.0, $row['viewToCartRate']);
        $this->assertSame(0.0, $row['cartToPurchaseRate']);
        $this->assertSame(0.0, $row['conversionRate']);
        $this->assertNull($row['avgRating']);
        $this->assertNull($row['lastInteractionAt']);
    }

    public function testBuildCartToPurchaseRateGuardedWhenNoCartsButHasPurchases(): void
    {
        // Edge case: purchases recorded without a corresponding cart-add
        // entry in the map (e.g. direct buy-now flow) — must not divide by zero.
        $product = $this->makeProduct(4, 'DirectBuy', '5.00', 1);
        $this->productRepository->method('findAll')->willReturn([$product]);

        $aggregation = $this->makeAggregation(countsByType: [
            'view' => [4 => ['count' => 10, 'qty' => 0]],
            'purchase' => [4 => ['count' => 2, 'qty' => 2]],
        ]);

        $builder = new ProductFeatureBuilder($this->productRepository, $aggregation);
        $rows = $builder->build();

        $this->assertSame(0.0, $rows[0]['cartToPurchaseRate']);
        $this->assertSame(0.2, $rows[0]['conversionRate']); // 2 / 10 still computed via views
    }
}
