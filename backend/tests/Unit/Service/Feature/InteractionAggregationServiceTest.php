<?php

namespace App\Tests\Unit\Service\Feature;

use App\Service\Feature\InteractionAggregationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\TestCase;

class InteractionAggregationServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private InteractionAggregationService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->service = new InteractionAggregationService($this->em);
    }

    /**
     * Builds a Query mock that returns $rows from getArrayResult() and
     * chains setParameter() back to itself, regardless of which/how many
     * parameters are set.
     */
    private function stubQuery(array $rows): Query
    {
        $query = $this->createMock(Query::class);
        $query->method('setParameter')->willReturnSelf();
        $query->method('getArrayResult')->willReturn($rows);

        return $query;
    }

    // -- countsByType --------------------------------------------------

    public function testCountsByTypeMapsAndCastsRowsAndSkipsNullId(): void
    {
        $rows = [
            ['id' => '5', 'cnt' => '3', 'qty' => '10'],
            ['id' => null, 'cnt' => '1', 'qty' => '1'], // ungrouped/null FK — must be dropped
        ];
        $query = $this->stubQuery($rows);
        $query->expects($this->once())->method('setParameter')->with('type', 'view')->willReturnSelf();
        $this->em->method('createQuery')->willReturn($query);

        $result = $this->service->countsByType('view', 'i.product');

        $this->assertSame([5 => ['count' => 3, 'qty' => 10]], $result);
    }

    public function testCountsByTypeAddsJoinWhenJoinProductIsTrue(): void
    {
        $query = $this->stubQuery([]);
        $this->em->expects($this->once())
            ->method('createQuery')
            ->with($this->callback(static fn (string $dql) => str_contains($dql, 'JOIN i.product p')))
            ->willReturn($query);

        $this->service->countsByType('view', 'p.brand', joinProduct: true);
    }

    public function testCountsByTypeOmitsJoinWhenJoinProductIsFalse(): void
    {
        $query = $this->stubQuery([]);
        $this->em->expects($this->once())
            ->method('createQuery')
            ->with($this->callback(static fn (string $dql) => !str_contains($dql, 'JOIN i.product p')))
            ->willReturn($query);

        $this->service->countsByType('view', 'i.product');
    }

    public function testCountsByTypeReturnsEmptyArrayWhenNoRows(): void
    {
        $this->em->method('createQuery')->willReturn($this->stubQuery([]));

        $this->assertSame([], $this->service->countsByType('purchase', 'i.product'));
    }

    // -- distinctUsersGroupedBy -----------------------------------------

    public function testDistinctUsersGroupedByMapsAndSkipsNullId(): void
    {
        $rows = [
            ['id' => '2', 'cnt' => '4'],
            ['id' => null, 'cnt' => '9'],
        ];
        $this->em->method('createQuery')->willReturn($this->stubQuery($rows));

        $result = $this->service->distinctUsersGroupedBy('p.category');

        $this->assertSame([2 => 4], $result);
    }

    // -- distinctEngagedGroupedBy ----------------------------------------

    public function testDistinctEngagedGroupedByMapsAndSkipsNullId(): void
    {
        $rows = [
            ['id' => '7', 'cnt' => '2'],
            ['id' => null, 'cnt' => '99'],
        ];
        $this->em->method('createQuery')->willReturn($this->stubQuery($rows));

        $result = $this->service->distinctEngagedGroupedBy('p.brand', 'i.user');

        $this->assertSame([7 => 2], $result);
    }

    // -- lastInteractionGroupedBy -----------------------------------------

    public function testLastInteractionGroupedByConvertsToDateTimeImmutableAndSkipsNullId(): void
    {
        $rows = [
            ['id' => '3', 'last' => '2024-01-15 08:30:00'],
            ['id' => null, 'last' => '2024-01-01 00:00:00'],
        ];
        $this->em->method('createQuery')->willReturn($this->stubQuery($rows));

        $result = $this->service->lastInteractionGroupedBy('i.product');

        $this->assertCount(1, $result);
        $this->assertArrayHasKey(3, $result);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result[3]);
        $this->assertSame('2024-01-15 08:30:00', $result[3]->format('Y-m-d H:i:s'));
    }

    // -- favoritesGroupedBy -----------------------------------------------

    public function testFavoritesGroupedByMapsAndSkipsNullId(): void
    {
        $rows = [
            ['id' => '1', 'cnt' => '6'],
            ['id' => null, 'cnt' => '2'],
        ];
        $this->em->method('createQuery')->willReturn($this->stubQuery($rows));

        $result = $this->service->favoritesGroupedBy('f.product', joinProduct: true);

        $this->assertSame([1 => 6], $result);
    }

    public function testFavoritesGroupedByAddsJoinWhenRequested(): void
    {
        $query = $this->stubQuery([]);
        $this->em->expects($this->once())
            ->method('createQuery')
            ->with($this->callback(static fn (string $dql) => str_contains($dql, 'JOIN f.product p')))
            ->willReturn($query);

        $this->service->favoritesGroupedBy('p.category', joinProduct: true);
    }

    // -- reviewsGroupedBy --------------------------------------------------

    public function testReviewsGroupedByMapsCountAndAverage(): void
    {
        $rows = [
            ['id' => '4', 'cnt' => '5', 'avgRating' => '4.5'],
            ['id' => null, 'cnt' => '1', 'avgRating' => '2.0'],
        ];
        $this->em->method('createQuery')->willReturn($this->stubQuery($rows));

        $result = $this->service->reviewsGroupedBy('r.product');

        $this->assertSame([4 => ['count' => 5, 'avg' => 4.5]], $result);
    }

    // -- orderStatsByUser ----------------------------------------------------

    public function testOrderStatsByUserMapsCountAndTotalAndSetsCartFilterParameter(): void
    {
        $rows = [
            ['id' => '9', 'cnt' => '3', 'total' => '150.75'],
        ];
        $query = $this->stubQuery($rows);
        $query->expects($this->once())->method('setParameter')->with('cart', 'cart')->willReturnSelf();
        $this->em->method('createQuery')->willReturn($query);

        $result = $this->service->orderStatsByUser();

        $this->assertSame([9 => ['count' => 3, 'total' => 150.75]], $result);
    }

    public function testOrderStatsByUserDoesNotSkipNullIdUnlikeOtherAggregates(): void
    {
        // Unlike every other grouped-aggregate method in this class,
        // orderStatsByUser() has no `if (null === $row['id']) continue;`
        // guard — a null id is cast straight to (int) 0 and kept as a row.
        // This documents that real (if surprising) behavior.
        $rows = [
            ['id' => null, 'cnt' => '1', 'total' => '20.00'],
        ];
        $this->em->method('createQuery')->willReturn($this->stubQuery($rows));

        $result = $this->service->orderStatsByUser();

        $this->assertSame([0 => ['count' => 1, 'total' => 20.0]], $result);
    }

    // -- productCountsBy* ------------------------------------------------

    public function testProductCountsByCategoryMapsAndSkipsNullId(): void
    {
        $rows = [['id' => '1', 'cnt' => '8'], ['id' => null, 'cnt' => '3']];
        $this->em->method('createQuery')->willReturn($this->stubQuery($rows));

        $this->assertSame([1 => 8], $this->service->productCountsByCategory());
    }

    public function testProductCountsByBrandMapsAndSkipsNullId(): void
    {
        $rows = [['id' => '2', 'cnt' => '4'], ['id' => null, 'cnt' => '3']];
        $this->em->method('createQuery')->willReturn($this->stubQuery($rows));

        $this->assertSame([2 => 4], $this->service->productCountsByBrand());
    }

    public function testProductCountsByProductTypeMapsAndSkipsNullId(): void
    {
        $rows = [['id' => '6', 'cnt' => '11'], ['id' => null, 'cnt' => '3']];
        $this->em->method('createQuery')->willReturn($this->stubQuery($rows));

        $this->assertSame([6 => 11], $this->service->productCountsByProductType());
    }

    // -- purchaseTimeSeries ------------------------------------------------

    public function testPurchaseTimeSeriesPreFillsEveryWeekAndAggregatesMatchingRows(): void
    {
        $weeks = 4;
        $since = new \DateTimeImmutable("-{$weeks} weeks");
        $week0 = $since->modify('monday this week');
        $week1 = $week0->modify('+1 week');

        $rows = [
            // Two purchases land in the same week (week0) and must be summed.
            ['createdAt' => $week0, 'value' => '3'],
            ['createdAt' => $week0->modify('+2 days'), 'value' => '2'],
            // A single purchase in week1, given as a raw string (not a
            // DateTimeInterface) to exercise the string-parsing branch.
            ['createdAt' => $week1->format('Y-m-d H:i:s'), 'value' => '7'],
        ];
        $this->em->method('createQuery')->willReturn($this->stubQuery($rows));

        $series = $this->service->purchaseTimeSeries('i.product', 42, $weeks);

        $this->assertCount($weeks, $series);
        // Oldest week first.
        $this->assertSame($week0->format('Y-m-d'), $series[0]['weekStart']);
        $this->assertSame($week1->format('Y-m-d'), $series[1]['weekStart']);
        $this->assertSame(5, $series[0]['unitsSold']); // 3 + 2
        $this->assertSame(7, $series[1]['unitsSold']);
        // The remaining pre-filled weeks with no purchases stay at zero.
        $this->assertSame(0, $series[2]['unitsSold']);
        $this->assertSame(0, $series[3]['unitsSold']);
    }

    public function testPurchaseTimeSeriesReturnsAllZeroBucketsWhenNoRows(): void
    {
        $weeks = 3;
        $this->em->method('createQuery')->willReturn($this->stubQuery([]));

        $series = $this->service->purchaseTimeSeries('i.user', 1, $weeks);

        $this->assertCount($weeks, $series);
        foreach ($series as $bucket) {
            $this->assertSame(0, $bucket['unitsSold']);
            $this->assertArrayHasKey('weekStart', $bucket);
        }
        // Ascending (oldest first).
        $this->assertLessThan($series[1]['weekStart'], $series[0]['weekStart']);
        $this->assertLessThan($series[2]['weekStart'], $series[1]['weekStart']);
    }

    public function testPurchaseTimeSeriesJoinsProductForNonProductGroupBy(): void
    {
        $query = $this->stubQuery([]);
        $this->em->expects($this->once())
            ->method('createQuery')
            ->with($this->callback(static fn (string $dql) => str_contains($dql, 'JOIN i.product p')))
            ->willReturn($query);

        $this->service->purchaseTimeSeries('p.category', 5, 2);
    }

    public function testPurchaseTimeSeriesOmitsJoinForDirectProductOrUserGroupBy(): void
    {
        $query = $this->stubQuery([]);
        $this->em->expects($this->once())
            ->method('createQuery')
            ->with($this->callback(static fn (string $dql) => !str_contains($dql, 'JOIN i.product p')))
            ->willReturn($query);

        $this->service->purchaseTimeSeries('i.product', 5, 2);
    }
}
