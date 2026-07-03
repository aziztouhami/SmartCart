<?php

namespace App\Recommendation\Service;

use App\Entity\Product;

/**
 * Engine B of the logged-in hybrid recommender — "similar to what you
 * already liked", built entirely from product features (category, brand,
 * type, attribute values) via ContentSimilarityService. Works for brand-new
 * products with zero interactions (unlike Engine A) and is easy to explain,
 * at the cost of being more repetitive/obvious.
 */
class ContentRecommendationService
{
    public function __construct(
        private ContentSimilarityService $contentSimilarity,
    ) {}

    /**
     * @param array<int, float> $userRatings seed productId => tasteScore (this user only)
     * @param array<int, Product> $productsById whole catalog, keyed by id
     * @return array<int, float> candidateProductId => content score
     */
    public function predictForUser(array $userRatings, array $productsById): array
    {
        $scores = [];

        foreach ($userRatings as $seedId => $tasteScore) {
            $seed = $productsById[$seedId] ?? null;
            if (!$seed || $tasteScore <= 0) {
                continue; // don't chase more of something this user disliked
            }

            foreach ($productsById as $candidateId => $candidate) {
                if ($candidateId === $seedId || isset($userRatings[$candidateId])) {
                    continue;
                }
                $similarity = $this->contentSimilarity->score($seed, $candidate);
                if ($similarity <= 0) {
                    continue;
                }
                $scores[$candidateId] = ($scores[$candidateId] ?? 0) + $similarity * $tasteScore;
            }
        }

        return $scores;
    }
}
