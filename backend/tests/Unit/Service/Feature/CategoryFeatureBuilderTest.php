<?php

namespace App\Tests\Unit\Service\Feature;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Service\Feature\CategoryFeatureBuilder;
use App\Service\Feature\InteractionAggregationService;
use PHPUnit\Framework\TestCase;

class CategoryFeatureBuilderTest extends TestCase
{
    private CategoryRepository $categoryRepository;

    protected function setUp(): void
    {
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
    }

    private function makeCategory(int $id, string $name): Category
    {
        $category = new Category();
        $ref = new \ReflectionProperty(Category::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($category, $id);
        $category->setName($name);

        return $category;
    }

    /**
     * @param array<string, array<int, array{count?: int, qty?: int}>> $countsByType keyed by interaction type ('view'|'cart'|'purchase')
     */
    private function makeAggregation(
        array $countsByType = [],
        array $productCounts = [],
        array $distinctUsers = [],
        array $favorites = [],
        array $reviews = [],
    ): InteractionAggregationService {
        $agg = $this->createMock(InteractionAggregationService::class);
        $agg->method('productCountsByCategory')->willReturn($productCounts);
        $agg->method('countsByType')->willReturnCallback(
            static fn (string $type, string $groupBy, bool $joinProduct = false) => $countsByType[$type] ?? []
        );
        $agg->method('distinctUsersGroupedBy')->willReturn($distinctUsers);
        $agg->method('favoritesGroupedBy')->willReturn($favorites);
        $agg->method('reviewsGroupedBy')->willReturn($reviews);

        return $agg;
    }

    public function testBuildReturnsEmptyArrayWhenNoCategories(): void
    {
        $this->categoryRepository->method('findAll')->willReturn([]);
        $builder = new CategoryFeatureBuilder($this->categoryRepository, $this->makeAggregation());

        $this->assertSame([], $builder->build());
    }

    public function testBuildComputesFullRowWithData(): void
    {
        $category = $this->makeCategory(1, 'Electronics');
        $this->categoryRepository->method('findAll')->willReturn([$category]);

        $aggregation = $this->makeAggregation(
            countsByType: [
                'view' => [1 => ['count' => 400, 'qty' => 0]],
                'cart' => [1 => ['count' => 80, 'qty' => 100]],
                'purchase' => [1 => ['count' => 40, 'qty' => 45]],
            ],
            productCounts: [1 => 12],
            distinctUsers: [1 => 30],
            favorites: [1 => 18],
            reviews: [1 => ['count' => 6, 'avg' => 3.333]],
        );

        $builder = new CategoryFeatureBuilder($this->categoryRepository, $aggregation);
        $rows = $builder->build();

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame(1, $row['categoryId']);
        $this->assertSame('Electronics', $row['name']);
        $this->assertSame(12, $row['productCount']);
        $this->assertSame(400, $row['views']);
        $this->assertSame(80, $row['cartAdds']);
        $this->assertSame(40, $row['purchases']);
        $this->assertSame(45, $row['purchaseQuantity']);
        $this->assertSame(30, $row['distinctUsersEngaged']);
        $this->assertSame(18, $row['favorites']);
        $this->assertSame(6, $row['reviewCount']);
        $this->assertSame(3.33, $row['avgRating']);
        $this->assertSame(0.1, $row['conversionRate']); // 40 / 400
    }

    public function testBuildHandlesZeroActivityWithoutDivisionByZero(): void
    {
        $category = $this->makeCategory(2, 'Empty');
        $this->categoryRepository->method('findAll')->willReturn([$category]);

        $builder = new CategoryFeatureBuilder($this->categoryRepository, $this->makeAggregation());
        $rows = $builder->build();

        $row = $rows[0];
        $this->assertSame(0, $row['productCount']);
        $this->assertSame(0, $row['views']);
        $this->assertSame(0, $row['purchases']);
        $this->assertNull($row['avgRating']);
        $this->assertSame(0.0, $row['conversionRate']);
    }

    public function testBuildMapsMultipleCategoriesIndependently(): void
    {
        $catA = $this->makeCategory(1, 'A');
        $catB = $this->makeCategory(2, 'B');
        $this->categoryRepository->method('findAll')->willReturn([$catA, $catB]);

        $aggregation = $this->makeAggregation(countsByType: [
            'view' => [1 => ['count' => 10, 'qty' => 0], 2 => ['count' => 20, 'qty' => 0]],
            'purchase' => [1 => ['count' => 5, 'qty' => 5], 2 => ['count' => 1, 'qty' => 1]],
        ]);

        $builder = new CategoryFeatureBuilder($this->categoryRepository, $aggregation);
        $rows = $builder->build();

        $this->assertSame(0.5, $rows[0]['conversionRate']); // 5/10
        $this->assertSame(0.05, $rows[1]['conversionRate']); // 1/20
    }
}
