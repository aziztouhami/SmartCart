<?php

namespace App\Recommendation\Service;

use App\Entity\User;
use App\Recommendation\Entity\ProductRelation;
use App\Recommendation\Repository\ColdStartRecommendationRepository;
use App\Recommendation\Repository\ProductRelationRepository;
use App\Recommendation\Repository\UserRecommendationRepository;
use App\Repository\GuestEventRepository;
use App\Repository\InteractionRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * Serves recommendations from whichever precomputed table fits the visitor:
 * - Logged in: user_recommendation, the offline CF + content hybrid blend
 *   (UserRecommendationBuilderService). Falls back to a live computation
 *   from product_relation if the batch job hasn't run for this user yet
 *   (e.g. they just signed up) so they're never stuck empty for a day.
 * - Guest with session history: product_relation, looked up live from
 *   their session's recent products.
 * - Anyone with absolutely no history yet (new guest, new account with no
 *   preferences): cold_start_recommendation — what new visitors tend to
 *   look at first, blended with what's trending right now.
 * Either way, serving is a fast indexed read, never the heavy modeling.
 */
class RecommendationServingService
{
    private const RETARGETING_BASE_SCORE = 2.0;

    public function __construct(
        private ProductRelationRepository $productRelationRepository,
        private UserRecommendationRepository $userRecommendationRepository,
        private ColdStartRecommendationRepository $coldStartRecommendationRepository,
        private ProductRepository $productRepository,
        private InteractionRepository $interactionRepository,
        private GuestEventRepository $guestEventRepository,
        private OrderRepository $orderRepository,
    ) {}

    /**
     * @return \App\Entity\Product[]
     */
    public function forUser(User $user, int $limit): array
    {
        $rows = $this->userRecommendationRepository->findForUser($user, $limit * 2);

        if (empty($rows)) {
            // No precomputed list yet (brand-new account, batch hasn't run) —
            // fall back to the same live lookup guests get, using this
            // user's interaction history instead of a session.
            $recentIds = [];
            foreach ($this->interactionRepository->findByUser($user, 15) as $interaction) {
                $recentIds[] = $interaction->getProduct()->getId();
            }
            $recentIds = array_values(array_unique($recentIds));

            return empty($recentIds)
                ? $this->coldStartLookup($limit)
                : $this->liveLookup($recentIds, $limit);
        }

        $purchasedIds = array_flip($this->orderRepository->findPurchasedProductIds($user));
        $candidateIds = array_filter(
            array_map(fn ($row) => $row['productId'], $rows),
            fn ($id) => !isset($purchasedIds[$id])
        );

        return $this->resolveAndFilterStock($candidateIds, $limit);
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
     * Product detail page recommendations for a single product: substitutes
     * ("similar") and goes-with items ("complementary"), each a direct
     * indexed lookup against the precomputed product_relation table.
     *
     * @return array{similar: \App\Entity\Product[], complementary: \App\Entity\Product[]}
     */
    public function forProduct(int $productId, int $limit): array
    {
        $similarIds = $this->productRelationRepository->findTopForProduct(
            $productId, ProductRelation::TYPE_SIMILAR, $limit * 2
        );
        $complementaryIds = $this->productRelationRepository->findTopForProduct(
            $productId, ProductRelation::TYPE_COMPLEMENTARY, $limit * 2
        );

        return [
            'similar' => $this->resolveAndFilterStock($similarIds, $limit),
            'complementary' => $this->resolveAndFilterStock($complementaryIds, $limit),
        ];
    }

    /**
     * @return \App\Entity\Product[]
     */
    private function coldStartLookup(int $limit): array
    {
        $ids = $this->coldStartRecommendationRepository->findTopProductIds($limit * 2);
        return $this->resolveAndFilterStock($ids, $limit);
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
