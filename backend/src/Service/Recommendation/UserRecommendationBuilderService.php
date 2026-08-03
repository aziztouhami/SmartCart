<?php

namespace App\Service\Recommendation;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\ColdStartRecommendationRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\PromotionRepository;
use App\Repository\UserRecommendationRepository;
use App\Repository\UserRepository;

/**
 * The offline "brain" behind logged-in hybrid recommendations — trains a
 * fresh CF model and blends it with content scoring via
 * HybridRecommendationScorer, applies business rules via
 * RecommendationBusinessRules (both shared with the live per-request path
 * in RecommendationServingService, which uses this same trained model via
 * its cache — see CachedCollaborativeFilteringModel). Brand-new users with
 * no history at all fall back to their stated category/brand preferences,
 * then to trending.
 */
class UserRecommendationBuilderService
{
    private const TOP_K_PER_USER = 20;

    public function __construct(
        private UserRepository $userRepository,
        private ProductRepository $productRepository,
        private OrderRepository $orderRepository,
        private PromotionRepository $promotionRepository,
        private UserRecommendationRepository $userRecommendationRepository,
        private ColdStartRecommendationRepository $coldStartRecommendationRepository,
        private CollaborativeFilteringService $collaborativeFiltering,
        private CachedCollaborativeFilteringModel $cachedCfModel,
        private HybridRecommendationScorer $hybridScorer,
        private RecommendationBusinessRules $businessRules,
    ) {
    }

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

        $tasteMatrix = $this->collaborativeFiltering->buildTasteMatrix();
        // Also (re)trains the model the live serving path reads from its
        // cache, so a rebuild is reflected there immediately.
        $model = $this->cachedCfModel->refresh($tasteMatrix);
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
                ++$fallbackCount;
                $source = (empty($user->getPreferredCategoryIds()) && empty($user->getPreferredBrandIds()))
                    ? 'trending'
                    : 'preferences';
            } else {
                $candidates = $this->hybridScorer->score($user, $userRatings, $model, $allProductIds, $productsById);
                ++$hybridCount;
                $source = 'hybrid';
            }

            $candidates = $this->applyBusinessRules($user, $candidates, $productsById, $promoMap);

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
     * @param array<int, Product> $productsById
     * @param array<int, float>   $coldStart    productId => "new visitors + trending" score
     *
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
     * @param array<int, float>   $candidates
     * @param array<int, Product> $productsById
     *
     * @return array<int, float>
     */
    private function applyBusinessRules(User $user, array $candidates, array $productsById, array $promoMap): array
    {
        $purchasedIds = array_flip($this->orderRepository->findPurchasedProductIds($user));
        $candidates = array_diff_key($candidates, $purchasedIds); // already own it

        return $this->businessRules->apply($candidates, $productsById, $promoMap, self::TOP_K_PER_USER);
    }
}
