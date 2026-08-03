<?php

namespace App\Service\Recommendation;

use App\Repository\ColdStartRecommendationRepository;
use App\Repository\GuestEventRepository;
use App\Repository\InteractionRepository;
use App\Repository\ProductRepository;

/**
 * Builds the one global list served to anyone with absolutely no
 * personalization signal at all — a brand-new guest with no session
 * events, or a brand-new account with no preferences and no history.
 * Blends two things: what brand-new visitors tend to look at first
 * ("first touch" popularity, across all past sessions/users) and what's
 * trending right now (recent activity, any product). Pure recency alone
 * was the old fallback; this is closer to "what actually tends to hook a
 * new visitor" rather than just "what's newest in the catalog". A small
 * seasonal boost (SeasonalBoostService) nudges categories tagged for the
 * current month, since this list has no personalization to lean on otherwise.
 */
class ColdStartRecommendationService
{
    private const TRENDING_WINDOW_DAYS = 14;
    private const TOP_K = 16;
    private const WEIGHT_FIRST_TOUCH = 0.5;
    private const WEIGHT_TRENDING = 0.5;
    private const BOOST_SEASONAL = 0.3; // smaller than the logged-in boost — this list has no personalization to begin with

    public function __construct(
        private GuestEventRepository $guestEventRepository,
        private InteractionRepository $interactionRepository,
        private ProductRepository $productRepository,
        private ColdStartRecommendationRepository $coldStartRecommendationRepository,
        private SeasonalBoostService $seasonalBoost,
    ) {
    }

    /**
     * @return array{rows: int}
     */
    public function rebuild(): array
    {
        $firstTouchIds = array_merge(
            $this->guestEventRepository->findFirstProductIdPerSession(),
            $this->interactionRepository->findFirstProductIdPerUser()
        );
        $firstTouchCounts = array_count_values($firstTouchIds);

        $since = new \DateTimeImmutable(sprintf('-%d days', self::TRENDING_WINDOW_DAYS));
        $trendingCounts = $this->guestEventRepository->countRecentByProduct($since);
        foreach ($this->interactionRepository->countRecentByProduct($since) as $productId => $count) {
            $trendingCounts[$productId] = ($trendingCounts[$productId] ?? 0) + $count;
        }

        $normFirstTouch = $this->normalize($firstTouchCounts);
        $normTrending = $this->normalize($trendingCounts);

        $combined = [];
        foreach (array_unique(array_merge(array_keys($normFirstTouch), array_keys($normTrending))) as $productId) {
            $combined[$productId] = self::WEIGHT_FIRST_TOUCH * ($normFirstTouch[$productId] ?? 0)
                + self::WEIGHT_TRENDING * ($normTrending[$productId] ?? 0);
        }

        if (empty($combined)) {
            // Genuinely no activity anywhere yet (fresh install) — newest
            // products are the only sensible thing left to show.
            $combined = $this->fallbackToNewest();
        }

        $this->applySeasonalBoost($combined);

        arsort($combined);
        $top = array_slice($combined, 0, self::TOP_K, true);

        $rows = [];
        foreach ($top as $productId => $score) {
            $rows[] = ['productId' => $productId, 'score' => round($score, 4)];
        }

        $this->coldStartRecommendationRepository->replaceAll($rows);

        return ['rows' => count($rows)];
    }

    /**
     * @param array<int, float> $scores productId => score, boosted in place
     */
    private function applySeasonalBoost(array &$scores): void
    {
        if (empty($scores)) {
            return;
        }

        $products = $this->productRepository->findBy(['id' => array_keys($scores)]);
        foreach ($products as $product) {
            if ($this->seasonalBoost->isInSeason($product)) {
                $scores[$product->getId()] += self::BOOST_SEASONAL;
            }
        }
    }

    /**
     * @return array<int, float>
     */
    private function fallbackToNewest(): array
    {
        $products = $this->productRepository->findNewestRanked();

        $scores = [];
        $rank = 0;
        foreach ($products as $product) {
            $scores[$product->getId()] = max(0, 1 - ($rank / max(count($products), 1)));
            ++$rank;
        }

        return $scores;
    }

    /**
     * @param array<int, int> $counts
     *
     * @return array<int, float>
     */
    private function normalize(array $counts): array
    {
        if (empty($counts)) {
            return [];
        }
        $max = max($counts);
        if ($max <= 0) {
            return [];
        }

        return array_map(fn ($v) => $v / $max, $counts);
    }
}
