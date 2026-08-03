<?php

namespace App\Tests\Unit\Recommendation;

use App\Entity\User;
use App\Repository\OrderRepository;
use App\Service\Recommendation\CollaborativeFilteringService;
use App\Service\Recommendation\ContentRecommendationService;
use App\Service\Recommendation\HybridRecommendationScorer;
use PHPUnit\Framework\TestCase;

class HybridRecommendationScorerTest extends TestCase
{
    private CollaborativeFilteringService $collaborativeFiltering;
    private ContentRecommendationService $contentRecommendation;
    private OrderRepository $orderRepository;
    private HybridRecommendationScorer $scorer;

    protected function setUp(): void
    {
        $this->collaborativeFiltering = $this->createMock(CollaborativeFilteringService::class);
        $this->contentRecommendation = $this->createMock(ContentRecommendationService::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->orderRepository->method('findPurchasedProductIds')->willReturn([]);

        $this->scorer = new HybridRecommendationScorer(
            $this->collaborativeFiltering,
            $this->contentRecommendation,
            $this->orderRepository,
        );
    }

    private function makeUser(int $id): User
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($user, $id);

        return $user;
    }

    public function testLeansOnContentForThinHistory(): void
    {
        // History below MIN_HISTORY_FOR_CF_LEAD (3) — content should dominate
        // (0.8 weight) over CF (0.2 weight) once both are normalized to 1.0.
        $this->collaborativeFiltering->method('predictForUser')->willReturn([10 => 1.0]);
        $this->contentRecommendation->method('predictForUser')->willReturn([10 => 1.0]);

        $result = $this->scorer->score($this->makeUser(1), [1 => 5.0], [], [10], []);

        $this->assertEqualsWithDelta(1.0, $result[10], 0.001); // 0.2*1.0 + 0.8*1.0
    }

    public function testLeansOnCollaborativeFilteringForRichHistory(): void
    {
        // History at/above MIN_HISTORY_FOR_CF_LEAD (3) — CF weight (0.6)
        // outweighs content (0.4).
        $this->collaborativeFiltering->method('predictForUser')->willReturn([10 => 1.0, 11 => 0.0]);
        $this->contentRecommendation->method('predictForUser')->willReturn([11 => 1.0, 10 => 0.0]);

        $userRatings = [1 => 5.0, 2 => 5.0, 3 => 5.0];
        $result = $this->scorer->score($this->makeUser(1), $userRatings, [], [10, 11], []);

        $this->assertGreaterThan($result[11], $result[10]);
    }

    public function testRetargetsViewedButNotPurchasedProducts(): void
    {
        $this->collaborativeFiltering->method('predictForUser')->willReturn([]);
        $this->contentRecommendation->method('predictForUser')->willReturn([]);

        // Product 1 was engaged with (taste score 4.0) but neither engine
        // returned it as a candidate (both exclude already-rated products by
        // design) — retargeting must reintroduce it.
        $result = $this->scorer->score($this->makeUser(1), [1 => 4.0], [], [], []);

        $this->assertSame(2.0, $result[1]); // 4.0 * RETARGETING_FACTOR (0.5)
    }

    public function testDoesNotRetargetPurchasedProducts(): void
    {
        $this->collaborativeFiltering->method('predictForUser')->willReturn([]);
        $this->contentRecommendation->method('predictForUser')->willReturn([]);
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->orderRepository->method('findPurchasedProductIds')->willReturn([1]);
        $scorer = new HybridRecommendationScorer($this->collaborativeFiltering, $this->contentRecommendation, $this->orderRepository);

        $result = $scorer->score($this->makeUser(1), [1 => 4.0], [], [], []);

        $this->assertArrayNotHasKey(1, $result);
    }

    public function testDoesNotRetargetNonPositiveTasteScores(): void
    {
        $this->collaborativeFiltering->method('predictForUser')->willReturn([]);
        $this->contentRecommendation->method('predictForUser')->willReturn([]);

        $result = $this->scorer->score($this->makeUser(1), [1 => 0.0], [], [], []);

        $this->assertArrayNotHasKey(1, $result);
    }
}
