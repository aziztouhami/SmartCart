<?php

namespace App\Service\Recommendation;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\OrderRepository;

/**
 * Blends Engine A (CollaborativeFilteringService — "users like you") with
 * Engine B (ContentRecommendationService — "similar to what you liked"),
 * using a switching weight that leans on content for thin histories and on
 * CF once a user has enough activity for it to be meaningful. Then
 * re-surfaces products the user engaged with (viewed/carted) but hasn't
 * bought yet — both engines exclude products the user already has a taste
 * score for by design, so retargeting has to reintroduce them explicitly.
 *
 * Shared by the batch hybrid rebuild (UserRecommendationBuilderService,
 * always trains a fresh CF model right before scoring) and the live
 * per-request path (RecommendationServingService, uses the cached model
 * from CachedCollaborativeFilteringModel) — same blend logic either way,
 * only the CF model's freshness differs.
 */
class HybridRecommendationScorer
{
    private const MIN_HISTORY_FOR_CF_LEAD = 3;
    private const WEIGHT_CF_THIN_HISTORY = 0.2;
    private const WEIGHT_CONTENT_THIN_HISTORY = 0.8;
    private const WEIGHT_CF_RICH_HISTORY = 0.6;
    private const WEIGHT_CONTENT_RICH_HISTORY = 0.4;

    // Viewed/carted-but-not-bought nudge, as a fraction of the original taste score.
    private const RETARGETING_FACTOR = 0.5;

    public function __construct(
        private CollaborativeFilteringService $collaborativeFiltering,
        private ContentRecommendationService $contentRecommendation,
        private OrderRepository $orderRepository,
    ) {
    }

    /**
     * @param array<int, float>   $userRatings   this user's productId => tasteScore
     * @param array               $cfModel       trained matrix-factorization model (fresh from
     *                                           CollaborativeFilteringService::train(), or the cached copy)
     * @param int[]               $allProductIds every candidate product id worth considering
     * @param array<int, Product> $productsById
     *
     * @return array<int, float> candidateProductId => blended score
     */
    public function score(User $user, array $userRatings, array $cfModel, array $allProductIds, array $productsById): array
    {
        $cfScores = $this->collaborativeFiltering->predictForUser($user->getId(), $userRatings, $cfModel, $allProductIds);
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
     * @param array<int, float> $scores
     *
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
