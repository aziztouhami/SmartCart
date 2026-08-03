<?php

namespace App\Tests\Unit\Service\Feature;

use App\Entity\Brand;
use App\Repository\BrandRepository;
use App\Service\Feature\BrandFeatureBuilder;
use App\Service\Feature\InteractionAggregationService;
use PHPUnit\Framework\TestCase;

class BrandFeatureBuilderTest extends TestCase
{
    private BrandRepository $brandRepository;

    protected function setUp(): void
    {
        $this->brandRepository = $this->createMock(BrandRepository::class);
    }

    private function makeBrand(int $id, string $name): Brand
    {
        $brand = new Brand();
        $ref = new \ReflectionProperty(Brand::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($brand, $id);
        $brand->setName($name);

        return $brand;
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
        $agg->method('productCountsByBrand')->willReturn($productCounts);
        $agg->method('countsByType')->willReturnCallback(
            static fn (string $type, string $groupBy, bool $joinProduct = false) => $countsByType[$type] ?? []
        );
        $agg->method('distinctUsersGroupedBy')->willReturn($distinctUsers);
        $agg->method('favoritesGroupedBy')->willReturn($favorites);
        $agg->method('reviewsGroupedBy')->willReturn($reviews);

        return $agg;
    }

    public function testBuildReturnsEmptyArrayWhenNoBrands(): void
    {
        $this->brandRepository->method('findAll')->willReturn([]);
        $builder = new BrandFeatureBuilder($this->brandRepository, $this->makeAggregation());

        $this->assertSame([], $builder->build());
    }

    public function testBuildComputesFullRowWithData(): void
    {
        $brand = $this->makeBrand(1, 'Acme');
        $this->brandRepository->method('findAll')->willReturn([$brand]);

        $aggregation = $this->makeAggregation(
            countsByType: [
                'view' => [1 => ['count' => 200, 'qty' => 0]],
                'cart' => [1 => ['count' => 50, 'qty' => 60]],
                'purchase' => [1 => ['count' => 20, 'qty' => 25]],
            ],
            productCounts: [1 => 7],
            distinctUsers: [1 => 15],
            favorites: [1 => 9],
            reviews: [1 => ['count' => 4, 'avg' => 4.5678]],
        );

        $builder = new BrandFeatureBuilder($this->brandRepository, $aggregation);
        $rows = $builder->build();

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame(1, $row['brandId']);
        $this->assertSame('Acme', $row['name']);
        $this->assertSame(7, $row['productCount']);
        $this->assertSame(200, $row['views']);
        $this->assertSame(50, $row['cartAdds']);
        $this->assertSame(20, $row['purchases']);
        $this->assertSame(25, $row['purchaseQuantity']);
        $this->assertSame(15, $row['distinctUsersEngaged']);
        $this->assertSame(9, $row['favorites']);
        $this->assertSame(4, $row['reviewCount']);
        $this->assertSame(4.57, $row['avgRating']); // rounded to 2 decimals
        $this->assertSame(0.1, $row['conversionRate']); // 20 / 200, rounded to 4 decimals
    }

    public function testBuildHandlesZeroActivityWithoutDivisionByZero(): void
    {
        $brand = $this->makeBrand(2, 'NoActivity');
        $this->brandRepository->method('findAll')->willReturn([$brand]);

        $builder = new BrandFeatureBuilder($this->brandRepository, $this->makeAggregation());
        $rows = $builder->build();

        $row = $rows[0];
        $this->assertSame(0, $row['productCount']);
        $this->assertSame(0, $row['views']);
        $this->assertSame(0, $row['cartAdds']);
        $this->assertSame(0, $row['purchases']);
        $this->assertSame(0, $row['purchaseQuantity']);
        $this->assertSame(0, $row['distinctUsersEngaged']);
        $this->assertSame(0, $row['favorites']);
        $this->assertSame(0, $row['reviewCount']);
        $this->assertNull($row['avgRating']);
        $this->assertSame(0.0, $row['conversionRate']); // guarded division by zero
    }

    public function testBuildComputesConversionRateWithPurchasesButNoViewsGuard(): void
    {
        // Defensive edge case: purchases present in the map but views absent —
        // conversionRate must still fall back to 0.0 rather than dividing by zero.
        $brand = $this->makeBrand(3, 'Weird');
        $this->brandRepository->method('findAll')->willReturn([$brand]);

        $aggregation = $this->makeAggregation(countsByType: [
            'purchase' => [3 => ['count' => 5, 'qty' => 5]],
        ]);

        $builder = new BrandFeatureBuilder($this->brandRepository, $aggregation);
        $rows = $builder->build();

        $this->assertSame(0.0, $rows[0]['conversionRate']);
        $this->assertSame(5, $rows[0]['purchases']);
    }

    public function testBuildMapsMultipleBrandsIndependently(): void
    {
        $brandA = $this->makeBrand(1, 'A');
        $brandB = $this->makeBrand(2, 'B');
        $this->brandRepository->method('findAll')->willReturn([$brandA, $brandB]);

        $aggregation = $this->makeAggregation(countsByType: [
            'view' => [1 => ['count' => 10, 'qty' => 0]],
            'purchase' => [1 => ['count' => 1, 'qty' => 1], 2 => ['count' => 2, 'qty' => 2]],
        ]);

        $builder = new BrandFeatureBuilder($this->brandRepository, $aggregation);
        $rows = $builder->build();

        $this->assertCount(2, $rows);
        $this->assertSame(0.1, $rows[0]['conversionRate']); // brand 1: 1/10
        $this->assertSame(0.0, $rows[1]['conversionRate']); // brand 2: views absent -> guarded
        $this->assertSame(2, $rows[1]['purchases']);
    }
}
