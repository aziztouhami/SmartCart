<?php

namespace App\Tests\Unit\Service\Feature;

use App\Entity\ProductType;
use App\Repository\ProductTypeRepository;
use App\Service\Feature\InteractionAggregationService;
use App\Service\Feature\ProductTypeFeatureBuilder;
use PHPUnit\Framework\TestCase;

class ProductTypeFeatureBuilderTest extends TestCase
{
    private ProductTypeRepository $productTypeRepository;

    protected function setUp(): void
    {
        $this->productTypeRepository = $this->createMock(ProductTypeRepository::class);
    }

    private function makeProductType(int $id, string $name): ProductType
    {
        $type = new ProductType();
        $ref = new \ReflectionProperty(ProductType::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($type, $id);
        $type->setName($name);

        return $type;
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
        $agg->method('productCountsByProductType')->willReturn($productCounts);
        $agg->method('countsByType')->willReturnCallback(
            static fn (string $type, string $groupBy, bool $joinProduct = false) => $countsByType[$type] ?? []
        );
        $agg->method('distinctUsersGroupedBy')->willReturn($distinctUsers);
        $agg->method('favoritesGroupedBy')->willReturn($favorites);
        $agg->method('reviewsGroupedBy')->willReturn($reviews);

        return $agg;
    }

    public function testBuildReturnsEmptyArrayWhenNoProductTypes(): void
    {
        $this->productTypeRepository->method('findAll')->willReturn([]);
        $builder = new ProductTypeFeatureBuilder($this->productTypeRepository, $this->makeAggregation());

        $this->assertSame([], $builder->build());
    }

    public function testBuildComputesFullRowWithData(): void
    {
        $type = $this->makeProductType(1, 'Smartphone');
        $this->productTypeRepository->method('findAll')->willReturn([$type]);

        $aggregation = $this->makeAggregation(
            countsByType: [
                'view' => [1 => ['count' => 500, 'qty' => 0]],
                'cart' => [1 => ['count' => 100, 'qty' => 120]],
                'purchase' => [1 => ['count' => 50, 'qty' => 55]],
            ],
            productCounts: [1 => 20],
            distinctUsers: [1 => 60],
            favorites: [1 => 25],
            reviews: [1 => ['count' => 10, 'avg' => 4.999]],
        );

        $builder = new ProductTypeFeatureBuilder($this->productTypeRepository, $aggregation);
        $rows = $builder->build();

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame(1, $row['productTypeId']);
        $this->assertSame('Smartphone', $row['name']);
        $this->assertSame(20, $row['productCount']);
        $this->assertSame(500, $row['views']);
        $this->assertSame(100, $row['cartAdds']);
        $this->assertSame(50, $row['purchases']);
        $this->assertSame(55, $row['purchaseQuantity']);
        $this->assertSame(60, $row['distinctUsersEngaged']);
        $this->assertSame(25, $row['favorites']);
        $this->assertSame(10, $row['reviewCount']);
        $this->assertSame(5.0, $row['avgRating']); // 4.999 rounds to 5.0
        $this->assertSame(0.1, $row['conversionRate']); // 50 / 500
    }

    public function testBuildHandlesZeroActivityWithoutDivisionByZero(): void
    {
        $type = $this->makeProductType(2, 'Empty');
        $this->productTypeRepository->method('findAll')->willReturn([$type]);

        $builder = new ProductTypeFeatureBuilder($this->productTypeRepository, $this->makeAggregation());
        $rows = $builder->build();

        $row = $rows[0];
        $this->assertSame(0, $row['productCount']);
        $this->assertSame(0, $row['views']);
        $this->assertSame(0, $row['purchases']);
        $this->assertNull($row['avgRating']);
        $this->assertSame(0.0, $row['conversionRate']);
    }

    public function testBuildMapsMultipleProductTypesIndependently(): void
    {
        $typeA = $this->makeProductType(1, 'A');
        $typeB = $this->makeProductType(2, 'B');
        $this->productTypeRepository->method('findAll')->willReturn([$typeA, $typeB]);

        $aggregation = $this->makeAggregation(countsByType: [
            'view' => [1 => ['count' => 200, 'qty' => 0]],
            'purchase' => [1 => ['count' => 4, 'qty' => 4], 2 => ['count' => 9, 'qty' => 9]],
        ]);

        $builder = new ProductTypeFeatureBuilder($this->productTypeRepository, $aggregation);
        $rows = $builder->build();

        $this->assertSame(0.02, $rows[0]['conversionRate']); // 4/200
        $this->assertSame(0.0, $rows[1]['conversionRate']); // views absent -> guarded
        $this->assertSame(9, $rows[1]['purchases']);
    }
}
