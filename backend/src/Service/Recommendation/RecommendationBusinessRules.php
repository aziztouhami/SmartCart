<?php

namespace App\Service\Recommendation;

use App\Entity\Product;
use App\Repository\PromotionRepository;

/**
 * Applies promo/new-arrival/seasonal score boosts to a set of candidate
 * recommendations, drops out-of-stock ones, then caps the result so no
 * more than DIVERSITY_CATEGORY_CAP products share a category (highest
 * score within each category wins the slots — ten near-identical phones
 * shouldn't crowd out everything else).
 *
 * Shared by the batch hybrid rebuild (UserRecommendationBuilderService) and
 * the live per-request path (RecommendationServingService) so both apply
 * the exact same rules — previously only the batch path had this, meaning
 * logged-in users' live recommendations silently skipped every promo/
 * new-arrival/seasonal boost and the diversity cap.
 */
class RecommendationBusinessRules
{
    private const DIVERSITY_CATEGORY_CAP = 3;
    private const BOOST_PROMOTION = 1.0;
    private const BOOST_NEW_ARRIVAL = 0.5;
    private const BOOST_SEASONAL = 0.5;
    private const NEW_ARRIVAL_WINDOW_DAYS = 7;

    public function __construct(
        private PromotionRepository $promotionRepository,
        private SeasonalBoostService $seasonalBoost,
    ) {
    }

    /**
     * @param array<int, float>      $candidates   productId => score, already purchase-excluded
     * @param array<int, Product>    $productsById must cover every key in $candidates
     * @param array<int, mixed>|null $promoMap     precomputed via PromotionRepository::findActiveForProducts() —
     *                                             pass this in when calling in a loop (e.g. once per user in a
     *                                             batch run) to avoid recomputing it on every call; omit for a
     *                                             one-off call, it's then computed from $candidates automatically
     * @param int|null               $topK         cap the final list size (diversity applies within this cap); null = no cap here
     *
     * @return array<int, float>
     */
    public function apply(array $candidates, array $productsById, ?array $promoMap = null, ?int $topK = null): array
    {
        if (empty($candidates)) {
            return [];
        }

        $promoMap ??= $this->promotionRepository->findActiveForProducts(
            array_intersect_key($productsById, $candidates)
        );
        $newArrivalCutoff = new \DateTimeImmutable('-'.self::NEW_ARRIVAL_WINDOW_DAYS.' days');

        $boosted = [];
        foreach ($candidates as $productId => $score) {
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

            $boosted[$productId] = $score;
        }

        arsort($boosted);

        $final = [];
        $perCategoryCount = [];
        foreach ($boosted as $productId => $score) {
            if (null !== $topK && count($final) >= $topK) {
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
}
