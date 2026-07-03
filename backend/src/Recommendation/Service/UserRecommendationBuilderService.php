<?php

namespace App\Recommendation\Service;

use App\Entity\Product;
use App\Entity\User;
use App\Recommendation\Repository\ColdStartRecommendationRepository;
use App\Recommendation\Repository\UserRecommendationRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\PromotionRepository;
use App\Repository\UserRepository;

/**
 * The offline "brain" behind logged-in hybrid recommendations. For each
 * user, blends Engine A (CollaborativeFilteringService — "users like you")
 * with Engine B (ContentRecommendationService — "similar to what you
 * liked"), using a switching weight that leans on content for thin
 * histories and on CF once a user has enough activity. Business rules
 * (exclude purchased, restock check, promo/new-arrival/seasonal boosts,
 * category diversity) are applied last. Brand-new users with no history at
 * all fall back to their stated category/brand preferences, then to trending.
 */
class UserRecommendationBuilderService
{
    private const MIN_HISTORY_FOR_CF_LEAD = 3;
    private const TOP_K_PER_USER = 20;
    private const DIVERSITY_CATEGORY_CAP = 3;

    private const WEIGHT_CF_THIN_HISTORY      = 0.2;
    private const WEIGHT_CONTENT_THIN_HISTORY = 0.8;
    private const WEIGHT_CF_RICH_HISTORY      = 0.6;
    private const WEIGHT_CONTENT_RICH_HISTORY = 0.4;

    private const RETARGETING_FACTOR = 0.5; // viewed/carted-but-not-bought nudge, as a fraction of the original taste score
    private const BOOST_PROMOTION = 1.0;
    private const BOOST_NEW_ARRIVAL = 0.5;
    private const BOOST_SEASONAL = 0.5;

    public function __construct(
        private UserRepository $userRepository,
        private ProductRepository $productRepository,
        private OrderRepository $orderRepository,
        private PromotionRepository $promotionRepository,
        private UserRecommendationRepository $userRecommendationRepository,
        private ColdStartRecommendationRepository $coldStartRecommendationRepository,
        private CollaborativeFilteringService $collaborativeFiltering,
        private ContentRecommendationService $contentRecommendation,
        private SeasonalBoostService $seasonalBoost,
    ) {}

    /**
     * @return array{users: int, hybrid: int, fallback: int, rows: int}
     */
    public function rebuild(): array
    {
        $products = $this->productRepository->findAll();
        $productsById = [];
        foreach ($products as $product) {
            $productsById[$product->getId()] = $product;
        }

        $promoMap = $this->promotionRepository->findActiveForProducts($products);
        $newArrivalCutoff = new \DateTimeImmutable('-7 days');

        $tasteMatrix = $this->collaborativeFiltering->buildTasteMatrix();
        $model = $this->collaborativeFiltering->train($tasteMatrix);
        $allProductIds = array_keys($productsById);

        // The shared "what new visitors tend to look at + what's trending"
        // list — same fallback guests with no session get, reused here so a
        // brand-new account with no preferences sees the same intelligent
        // default instead of a separate, simpler recency-only list.
        $coldStart = $this->buildColdStartScores();

        $rows = [];
        $hybridCount = 0;
        $fallbackCount = 0;

        foreach ($this->userRepository->findAll() as $user) {
            $userRatings = $tasteMatrix[$user->getId()] ?? [];

            if (empty($userRatings)) {
                $candidates = $this->buildColdStartCandidates($user, $productsById, $coldStart);
                $fallbackCount++;
                $source = (empty($user->getPreferredCategoryIds()) && empty($user->getPreferredBrandIds()))
                    ? 'trending'
                    : 'preferences';
            } else {
                $candidates = $this->buildHybridCandidates($user, $userRatings, $model, $allProductIds, $productsById);
                $hybridCount++;
                $source = 'hybrid';
            }

            $candidates = $this->applyBusinessRules($user, $candidates, $productsById, $promoMap, $newArrivalCutoff);

            foreach ($candidates as $productId => $score) {
                $rows[] = [
                    'userId' => $user->getId(),
                    'productId' => $productId,
                    'score' => round($score, 4),
                    'source' => $source,
                ];
            }
        }

        $this->userRecommendationRepository->replaceAll($rows);

        return [
            'users' => $hybridCount + $fallbackCount,
            'hybrid' => $hybridCount,
            'fallback' => $fallbackCount,
            'rows' => count($rows),
        ];
    }

    /**
     * @param array<int, float> $userRatings
     * @param array $model trained matrix-factorization model
     * @param int[] $allProductIds
     * @param array<int, Product> $productsById
     * @return array<int, float> candidateProductId => blended score
     */
    private function buildHybridCandidates(User $user, array $userRatings, array $model, array $allProductIds, array $productsById): array
    {
        $cfScores = $this->collaborativeFiltering->predictForUser($user->getId(), $userRatings, $model, $allProductIds);
        $contentScores = $this->contentRecommendation->predictForUser($userRatings, $productsById);

        $historySize = count($userRatings);
        if ($historySize < self::MIN_HISTORY_FOR_CF_LEAD) {
            [$wCf, $wContent] = [self::WEIGHT_CF_THIN_HISTORY, self::WEIGHT_CONTENT_THIN_HISTORY];
        } else {
            [$wCf, $wContent] = [self::WEIGHT_CF_RICH_HISTORY, self::WEIGHT_CONTENT_RICH_HISTORY];
        }

        $normCf = $this->normalize($cfScores);
        $normContent = $this->normalize($contentScores);

        $blended = [];
        foreach (array_unique(array_merge(array_keys($normCf), array_keys($normContent))) as $candidateId) {
            $blended[$candidateId] = $wCf * ($normCf[$candidateId] ?? 0) + $wContent * ($normContent[$candidateId] ?? 0);
        }

        // Retargeting: products this user engaged with (viewed and/or
        // carted) but never bought stay eligible too, instead of vanishing
        // the moment they're "rated" — both engines above exclude them by
        // design, so they have to be reintroduced explicitly here.
        $purchasedIds = array_flip($this->orderRepository->findPurchasedProductIds($user));
        foreach ($userRatings as $productId => $tasteScore) {
            if (isset($purchasedIds[$productId]) || $tasteScore <= 0) {
                continue;
            }
            $blended[$productId] = max($blended[$productId] ?? 0, $tasteScore * self::RETARGETING_FACTOR);
        }

        return $blended;
    }

    /**
     * @param array<int, Product> $productsById
     * @param array<int, float> $coldStart productId => "new visitors + trending" score
     * @return array<int, float>
     */
    private function buildColdStartCandidates(User $user, array $productsById, array $coldStart): array
    {
        $categoryIds = $user->getPreferredCategoryIds();
        $brandIds = $user->getPreferredBrandIds();

        if (empty($categoryIds) && empty($brandIds)) {
            return $coldStart; // Step 8 fallback #2: nothing stated — what new visitors usually look at + what's trending
        }

        // Step 8 fallback #1: content-based on stated preferences, ranked by
        // how many preferences a product matches (both category AND brand
        // outranks either alone) with the cold-start score as a tie-breaker.
        $candidates = [];
        foreach ($productsById as $productId => $product) {
            $inPreferredCategory = $product->getCategory() && in_array($product->getCategory()->getId(), $categoryIds, true);
            $inPreferredBrand = $product->getBrand() && in_array($product->getBrand()->getId(), $brandIds, true);
            if (!$inPreferredCategory && !$inPreferredBrand) {
                continue;
            }
            $matchScore = ($inPreferredCategory ? 1.0 : 0) + ($inPreferredBrand ? 1.0 : 0);
            $candidates[$productId] = $matchScore + 0.5 * ($coldStart[$productId] ?? 0);
        }

        return $candidates;
    }

    /**
     * Reads the precomputed "what new visitors tend to look at + what's
     * trending" list (ColdStartRecommendationService) — the same fallback
     * served to guests with no session, reused here for consistency. Falls
     * back to plain recency if that table hasn't been built yet for some
     * reason (e.g. running this command standalone before the cold-start
     * step), so cold-start users are never left with literally nothing.
     *
     * @return array<int, float>
     */
    private function buildColdStartScores(): array
    {
        return $this->coldStartRecommendationRepository->findTopWithScores(20);
    }

    /**
     * @param array<int, float> $candidates
     * @param array<int, Product> $productsById
     * @return array<int, float>
     */
    private function applyBusinessRules(
        User $user,
        array $candidates,
        array $productsById,
        array $promoMap,
        \DateTimeImmutable $newArrivalCutoff
    ): array {
        $purchasedIds = array_flip($this->orderRepository->findPurchasedProductIds($user));

        $filtered = [];
        foreach ($candidates as $productId => $score) {
            if (isset($purchasedIds[$productId])) {
                continue; // already own it
            }
            $product = $productsById[$productId] ?? null;
            if (!$product || $product->getStock() <= 0) {
                continue; // out of stock
            }

            if (isset($promoMap[$productId])) {
                $score += self::BOOST_PROMOTION;
            }
            if ($product->getCreatedAt() >= $newArrivalCutoff) {
                $score += self::BOOST_NEW_ARRIVAL;
            }
            if ($this->seasonalBoost->isInSeason($product)) {
                $score += self::BOOST_SEASONAL;
            }

            $filtered[$productId] = $score;
        }

        arsort($filtered);

        // Diversity: cap how many of the final list can share a category so
        // ten near-identical phones don't crowd out everything else.
        $final = [];
        $perCategoryCount = [];
        foreach ($filtered as $productId => $score) {
            if (count($final) >= self::TOP_K_PER_USER) {
                break;
            }
            $categoryId = $productsById[$productId]?->getCategory()?->getId() ?? 0;
            if (($perCategoryCount[$categoryId] ?? 0) >= self::DIVERSITY_CATEGORY_CAP) {
                continue;
            }
            $perCategoryCount[$categoryId] = ($perCategoryCount[$categoryId] ?? 0) + 1;
            $final[$productId] = $score;
        }

        return $final;
    }

    /**
     * @param array<int, float> $scores
     * @return array<int, float> same keys, values scaled to 0..1
     */
    private function normalize(array $scores): array
    {
        if (empty($scores)) {
            return [];
        }
        $max = max($scores);
        if ($max <= 0) {
            return array_map(fn () => 0.0, $scores);
        }
        return array_map(fn ($v) => max(0, $v) / $max, $scores);
    }
}
