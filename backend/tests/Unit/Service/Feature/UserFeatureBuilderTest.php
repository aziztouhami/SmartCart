<?php

namespace App\Tests\Unit\Service\Feature;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Feature\InteractionAggregationService;
use App\Service\Feature\UserFeatureBuilder;
use PHPUnit\Framework\TestCase;

class UserFeatureBuilderTest extends TestCase
{
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
    }

    private function makeUser(int $id, string $email, ?\DateTimeImmutable $createdAt = null): User
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($user, $id);
        $user->setEmail($email);
        $user->setCreatedAt($createdAt ?? new \DateTimeImmutable());

        return $user;
    }

    /**
     * @param array<string, array<int, array{count?: int, qty?: int}>> $countsByType    keyed by interaction type ('view'|'cart'|'purchase')
     * @param array<int, \DateTimeImmutable>                           $lastSeen
     * @param array<string, array<int, int>>                           $distinctEngaged keyed by distinct field ('p.category'|'p.brand')
     */
    private function makeAggregation(
        array $countsByType = [],
        array $orderStats = [],
        array $favorites = [],
        array $reviews = [],
        array $lastSeen = [],
        array $distinctEngaged = [],
    ): InteractionAggregationService {
        $agg = $this->createMock(InteractionAggregationService::class);
        $agg->method('countsByType')->willReturnCallback(
            static fn (string $type, string $groupBy, bool $joinProduct = false) => $countsByType[$type] ?? []
        );
        $agg->method('orderStatsByUser')->willReturn($orderStats);
        $agg->method('favoritesGroupedBy')->willReturn($favorites);
        $agg->method('reviewsGroupedBy')->willReturn($reviews);
        $agg->method('lastInteractionGroupedBy')->willReturn($lastSeen);
        $agg->method('distinctEngagedGroupedBy')->willReturnCallback(
            static fn (string $distinctField, string $groupBy) => $distinctEngaged[$distinctField] ?? []
        );

        return $agg;
    }

    public function testBuildReturnsEmptyArrayWhenNoUsers(): void
    {
        $this->userRepository->method('findAll')->willReturn([]);
        $builder = new UserFeatureBuilder($this->userRepository, $this->makeAggregation());

        $this->assertSame([], $builder->build());
    }

    public function testBuildComputesFullRowWithData(): void
    {
        $user = $this->makeUser(1, 'jane@example.com', new \DateTimeImmutable('-100 days'));
        $user->setIsVerified(true);
        $user->setMarketingOptIn(true);
        $this->userRepository->method('findAll')->willReturn([$user]);

        $lastActiveAt = new \DateTimeImmutable('-5 days');
        $aggregation = $this->makeAggregation(
            countsByType: [
                'view' => [1 => ['count' => 50, 'qty' => 0]],
                'cart' => [1 => ['count' => 10, 'qty' => 12]],
                'purchase' => [1 => ['count' => 4, 'qty' => 5]],
            ],
            orderStats: [1 => ['count' => 3, 'total' => 150.555]],
            favorites: [1 => 7],
            reviews: [1 => ['count' => 2, 'avg' => 3.666]],
            lastSeen: [1 => $lastActiveAt],
            distinctEngaged: [
                'p.category' => [1 => 6],
                'p.brand' => [1 => 4],
            ],
        );

        $builder = new UserFeatureBuilder($this->userRepository, $aggregation);
        $rows = $builder->build();

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame(1, $row['userId']);
        $this->assertSame('jane@example.com', $row['email']);
        $this->assertTrue($row['isVerified']);
        $this->assertTrue($row['marketingOptIn']);
        $this->assertSame(100, $row['accountAgeDays']);
        $this->assertSame(50, $row['views']);
        $this->assertSame(10, $row['cartAdds']);
        $this->assertSame(4, $row['purchases']);
        $this->assertSame(5, $row['purchaseQuantity']);
        $this->assertSame(3, $row['orderCount']);
        $this->assertSame(150.56, $row['totalSpent']); // rounded to 2 decimals
        $this->assertSame(50.19, $row['avgOrderValue']); // round(150.555 / 3, 2)
        $this->assertSame(7, $row['favoriteCount']);
        $this->assertSame(2, $row['reviewCount']);
        $this->assertSame(3.67, $row['avgRatingGiven']);
        $this->assertSame(6, $row['distinctCategoriesEngaged']);
        $this->assertSame(4, $row['distinctBrandsEngaged']);
        $this->assertSame(5, $row['daysSinceLastActivity']);
    }

    public function testBuildHandlesUserWithNoOrdersOrActivity(): void
    {
        $user = $this->makeUser(2, 'noone@example.com');
        $user->setIsVerified(false);
        $this->userRepository->method('findAll')->willReturn([$user]);

        $builder = new UserFeatureBuilder($this->userRepository, $this->makeAggregation());
        $rows = $builder->build();

        $row = $rows[0];
        $this->assertFalse($row['isVerified']);
        $this->assertFalse($row['marketingOptIn']);
        $this->assertSame(0, $row['orderCount']);
        $this->assertSame(0.0, $row['totalSpent']);
        $this->assertSame(0.0, $row['avgOrderValue']); // guarded division by zero
        $this->assertSame(0, $row['favoriteCount']);
        $this->assertSame(0, $row['reviewCount']);
        $this->assertNull($row['avgRatingGiven']);
        $this->assertSame(0, $row['distinctCategoriesEngaged']);
        $this->assertSame(0, $row['distinctBrandsEngaged']);
        $this->assertNull($row['daysSinceLastActivity']); // no last-seen entry -> null, not 0
    }

    public function testBuildMapsMultipleUsersIndependently(): void
    {
        $userA = $this->makeUser(1, 'a@example.com');
        $userB = $this->makeUser(2, 'b@example.com');
        $this->userRepository->method('findAll')->willReturn([$userA, $userB]);

        $aggregation = $this->makeAggregation(orderStats: [
            1 => ['count' => 2, 'total' => 100.0],
            2 => ['count' => 0, 'total' => 0.0], // shouldn't normally appear, but guards against it
        ]);

        $builder = new UserFeatureBuilder($this->userRepository, $aggregation);
        $rows = $builder->build();

        $this->assertSame(50.0, $rows[0]['avgOrderValue']); // 100 / 2
        $this->assertSame(0.0, $rows[1]['avgOrderValue']); // orderCount 0 -> guarded
    }
}
