<?php

namespace App\Service\Recommendation;

use App\Entity\Interaction;
use App\Entity\User;
use App\Entity\ProductRelation;
use App\Repository\ColdStartRecommendationRepository;
use App\Repository\ProductRelationRepository;
use App\Repository\UserRecommendationRepository;
use App\Repository\GuestEventRepository;
use App\Repository\InteractionRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * Serves recommendations from the most appropriate strategy for each visitor:
 * - Logged in: live content-based scoring from the user's full interaction
 *   history, recomputed on every request so every new view, cart add,
 *   purchase, or rating is immediately reflected in the results.
 * - Guest with session history: live product_relation lookup weighted by
 *   how recently each product was browsed.
 * - No history at all (new guest, new account): cold_start_recommendation —
 *   what new visitors tend to look at first, blended with what's trending.
 */
class RecommendationServingService
{
    // Taste signal weights used when building the real-time content score
    // for a logged-in user. Purchases outweigh views because explicit buying
    // intent is the strongest signal; cart sits between the two.
    private const TASTE_WEIGHTS = [
        'view'     => 0.5,
        'cart'     => 1.5,
        'purchase' => 3.0,
        'rating'   => 2.0,
    ];

    // Minimum score a complementary relation must have to be shown on the
    // product detail page. Filters out coincidental co-occurrence noise
    // that would produce irrelevant "Frequently Bought Together" results.
    private const COMPLEMENTARY_MIN_SCORE = 0.1;

    private const RETARGETING_BASE_SCORE = 2.0;

    public function __construct(
        private ProductRelationRepository $productRelationRepository,
        private UserRecommendationRepository $userRecommendationRepository,
        private ColdStartRecommendationRepository $coldStartRecommendationRepository,
        private ProductRepository $productRepository,
        private InteractionRepository $interactionRepository,
        private GuestEventRepository $guestEventRepository,
        private OrderRepository $orderRepository,
        private ContentRecommendationService $contentRecommendation,
    ) {}

    /**
     * Computes personalized product recommendations for a logged-in user
     * from their full interaction history in real-time.
     *
     * Every engagement (view, cart add, purchase, rating) is factored in
     * immediately — the score reflects whatever the user did up to this
     * exact request, with no batch-job lag.
     *
     * @return \App\Entity\Product[]
     */
    public function forUser(User $user, int $limit): array
    {
        $interactions = $this->interactionRepository->findByUser($user, 200);

        if (empty($interactions)) {
            return $this->coldStartLookup($limit);
        }

        $tasteScores = $this->buildTasteScores($interactions);

        // Load the full catalogue so content scoring can compare any product
        // to any other. At catalog scale (hundreds/low thousands of products)
        // this is a single indexed read within the request budget.
        $productsById = [];
        foreach ($this->productRepository->findAll() as $product) {
            $productsById[$product->getId()] = $product;
        }

        // Content-based pass: find products similar to what the user engaged with.
        $scores = $this->contentRecommendation->predictForUser($tasteScores, $productsById);

        // Retargeting pass: re-surface products the user viewed or carted but
        // hasn't purchased yet — they showed clear intent, worth nudging back.
        // Capped at 30 % of the taste weight so genuinely new related products
        // can still outrank "you already saw this".
        $purchasedIds = array_flip($this->orderRepository->findPurchasedProductIds($user));
        foreach ($tasteScores as $productId => $tasteScore) {
            if (!isset($purchasedIds[$productId])) {
                $scores[$productId] = max($scores[$productId] ?? 0.0, $tasteScore * 0.3);
            }
        }

        // Purchased products have already left the funnel — exclude them so
        // the list focuses on what the user might still want.
        foreach ($purchasedIds as $productId => $_) {
            unset($scores[$productId]);
        }

        arsort($scores);

        return $this->resolveAndFilterStock(array_keys($scores), $limit);
    }

    /**
     * @return \App\Entity\Product[]
     */
    public function forGuest(Request $request, int $limit): array
    {
        $sessionId = trim((string) $request->headers->get('X-Session-Id'));
        $recentIds = $sessionId === '' ? [] : $this->guestEventRepository->findRecentProductIdsBySession($sessionId, 15);

        // True cold start (no session yet, or a session with no events) —
        // show what brand-new visitors tend to look at, blended with what's
        // trending, rather than nothing at all.
        return empty($recentIds)
            ? $this->coldStartLookup($limit)
            : $this->liveLookup($recentIds, $limit);
    }

    /**
     * Product detail page recommendations for a single product.
     *
     * "You May Also Like" (similar) is computed in real-time from product
     * features (category, brand, type, attributes) via ContentSimilarityService,
     * so it always works regardless of whether the batch job has ever run.
     *
     * "Frequently Bought Together" (complementary) requires behavioral
     * co-occurrence data from the batch job (app:rebuild-recommendations).
     * It is intentionally empty when that data does not exist or the product
     * has no co-purchase history — irrelevant pairs must not be shown.
     *
     * @return array{similar: \App\Entity\Product[], complementary: \App\Entity\Product[]}
     */
    public function forProduct(int $productId, int $limit): array
    {
        // "Frequently Bought Together" — batch co-occurrence only.
        $complementaryIds = $this->productRelationRepository->findTopForProduct(
            $productId, ProductRelation::TYPE_COMPLEMENTARY, $limit * 2, self::COMPLEMENTARY_MIN_SCORE
        );

        // "You May Also Like" — real-time content similarity, always available.
        $similar = $this->findSimilarInRealTime($productId, $limit);

        return [
            'similar'       => $similar,
            'complementary' => $this->resolveAndFilterStock($complementaryIds, $limit),
        ];
    }

    /**
     * Finds products most similar to the given product using content-based
     * similarity (category, brand, type, shared attributes). Always returns
     * exactly $limit in-stock products: content-scored candidates first, then
     * newest products fill any remaining slots so the section is never short.
     *
     * @return \App\Entity\Product[]
     */
    private function findSimilarInRealTime(int $productId, int $limit): array
    {
        $allProducts = $this->productRepository->findAll();
        $productsById = [];
        foreach ($allProducts as $p) {
            $productsById[$p->getId()] = $p;
        }

        if (!isset($productsById[$productId])) {
            return [];
        }

        // Primary pass: content-scored candidates sorted by similarity.
        $scores = $this->contentRecommendation->predictForUser([$productId => 1.0], $productsById);
        arsort($scores);
        $similar = $this->resolveAndFilterStock(array_keys($scores), $limit);

        if (count($similar) >= $limit) {
            return $similar;
        }

        // Fill remaining slots with newest in-stock products, skipping the
        // current product and anything already included in $similar.
        $alreadyIncluded = array_flip(array_map(fn ($p) => $p->getId(), $similar));
        $alreadyIncluded[$productId] = true;

        $newest = $this->productRepository->findNewestRanked();
        foreach ($newest as $candidate) {
            if (count($similar) >= $limit) {
                break;
            }
            if (isset($alreadyIncluded[$candidate->getId()]) || $candidate->getStock() <= 0) {
                continue;
            }
            $similar[] = $candidate;
            $alreadyIncluded[$candidate->getId()] = true;
        }

        return $similar;
    }

    /**
     * @return \App\Entity\Product[]
     */
    private function coldStartLookup(int $limit): array
    {
        $ids = $this->coldStartRecommendationRepository->findTopProductIds($limit * 2);
        if (!empty($ids)) {
            return $this->resolveAndFilterStock($ids, $limit);
        }

        // Batch job hasn't run yet — fall back to newest products so the
        // recommendation section on the home page is never shown as empty.
        $newestIds = array_map(fn ($p) => $p->getId(), $this->productRepository->findNewestRanked());
        return $this->resolveAndFilterStock($newestIds, $limit);
    }

    /**
     * Live product_relation lookup from a list of recently-engaged product
     * ids, most recent first — recency-weighted. Also re-adds the recent
     * products themselves as candidates (something viewed/carted but not
     * bought is still worth re-surfacing; only a purchase ends interest) —
     * they're never in $relations otherwise, since that only contains
     * *other* products related to them, not the seeds themselves.
     *
     * @param int[] $recentProductIds
     * @return \App\Entity\Product[]
     */
    private function liveLookup(array $recentProductIds, int $limit): array
    {
        if (empty($recentProductIds)) {
            return [];
        }

        $relations = $this->productRelationRepository->findRelationsForProducts($recentProductIds, 20);

        // Most recent product gets full weight; each older one decays —
        // "recent = stronger intent".
        $recencyWeight = [];
        foreach ($recentProductIds as $rank => $productId) {
            $recencyWeight[$productId] = 1 / (1 + $rank * 0.4);
        }

        $combined = [];

        // Retargeting: the visited products themselves, scaled down so genuinely
        // new related items can still outrank "you already saw this".
        foreach ($recentProductIds as $productId) {
            $combined[$productId] = self::RETARGETING_BASE_SCORE * $recencyWeight[$productId];
        }

        foreach ($relations as $relation) {
            $weight = $recencyWeight[$relation['productId']] ?? 0.3;
            $relatedId = $relation['relatedProductId'];
            $combined[$relatedId] = ($combined[$relatedId] ?? 0) + $relation['score'] * $weight;
        }

        arsort($combined);

        return $this->resolveAndFilterStock(array_keys($combined), $limit);
    }

    /**
     * Converts raw interaction entities into a productId => tasteScore map.
     * Multiple interactions on the same product accumulate (e.g. three views
     * add 1.5 to the score before any purchase is counted).
     *
     * @param Interaction[] $interactions
     * @return array<int, float>
     */
    private function buildTasteScores(array $interactions): array
    {
        $scores = [];
        foreach ($interactions as $interaction) {
            $product = $interaction->getProduct();
            $type    = $interaction->getType();
            if (!$product || !$type) {
                continue;
            }
            $productId = $product->getId();
            $weight    = self::TASTE_WEIGHTS[$type] ?? 0.5;
            $scores[$productId] = ($scores[$productId] ?? 0.0) + $weight;
        }
        return $scores;
    }

    /**
     * @param int[] $candidateIds ranked, most relevant first
     * @return \App\Entity\Product[]
     */
    private function resolveAndFilterStock(array $candidateIds, int $limit): array
    {
        $candidateIds = array_slice(array_values($candidateIds), 0, $limit * 3);
        if (empty($candidateIds)) {
            return [];
        }

        $products = $this->productRepository->findBy(['id' => $candidateIds]);
        $byId = [];
        foreach ($products as $product) {
            $byId[$product->getId()] = $product;
        }

        $ordered = [];
        foreach ($candidateIds as $productId) {
            $product = $byId[$productId] ?? null;
            if (!$product || $product->getStock() <= 0) {
                continue; // out of stock — not worth recommending
            }
            $ordered[] = $product;
            if (count($ordered) >= $limit) {
                break;
            }
        }

        return $ordered;
    }
}
