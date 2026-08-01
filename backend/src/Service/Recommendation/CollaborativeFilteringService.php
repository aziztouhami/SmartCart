<?php

namespace App\Service\Recommendation;

use App\ML\MatrixFactorizationTrainer;
use App\Repository\InteractionRepository;

/**
 * Engine A of the logged-in hybrid recommender — "users like you" /
 * "users who liked this also liked that". Purely behavioral: it knows
 * nothing about category, brand, or features, only the user-item taste
 * matrix built from view/cart/purchase/rating signals.
 *
 * Trained as a latent-factor matrix factorization model (MatrixFactorizationTrainer)
 * rather than plain item-item cosine similarity — the model learns a small
 * vector of factors per user and per product from the observed taste
 * scores, then predicts a score for *any* product directly. That's what
 * lets it surface non-obvious recommendations Engine B's content
 * similarity never would — and also why it's useless for a user or
 * product with no history yet (cold start), which is what Engine B and the
 * preference fallback cover instead.
 */
class CollaborativeFilteringService
{
    private const WEIGHT_VIEW     = 1.0;
    private const WEIGHT_CART     = 3.0;
    private const WEIGHT_PURCHASE = 5.0;

    private const RATING_GOOD_THRESHOLD = 60; // Review::rating is 0-100
    private const RATING_BAD_THRESHOLD  = 40;
    private const WEIGHT_RATING_GOOD    = 5.0;
    private const WEIGHT_RATING_BAD     = -3.0;
    private const WEIGHT_RATING_NEUTRAL = 1.0;

    public function __construct(
        private InteractionRepository $interactionRepository,
        private MatrixFactorizationTrainer $matrixFactorization,
    ) {}

    /**
     * @return array<int, array<int, float>> userId => [productId => tasteScore]
     */
    public function buildTasteMatrix(): array
    {
        $matrix = [];
        foreach ($this->interactionRepository->findAllForTasteMatrix() as $row) {
            $score = $this->tasteScore($row['type'], $row['value']);
            $matrix[$row['userId']][$row['productId']] = ($matrix[$row['userId']][$row['productId']] ?? 0) + $score;
        }
        return $matrix;
    }

    private function tasteScore(string $type, ?int $value): float
    {
        return match ($type) {
            'cart' => self::WEIGHT_CART,
            'purchase' => self::WEIGHT_PURCHASE,
            'rating' => match (true) {
                $value === null => self::WEIGHT_RATING_NEUTRAL,
                $value >= self::RATING_GOOD_THRESHOLD => self::WEIGHT_RATING_GOOD,
                $value < self::RATING_BAD_THRESHOLD => self::WEIGHT_RATING_BAD,
                default => self::WEIGHT_RATING_NEUTRAL,
            },
            default => self::WEIGHT_VIEW, // view
        };
    }

    /**
     * The actual "learning" step: fits per-user and per-product latent
     * factor vectors that best explain the observed taste matrix.
     *
     * @param array<int, array<int, float>> $tasteMatrix
     * @return array the trained model, opaque — pass straight to predictForUser()
     */
    public function train(array $tasteMatrix): array
    {
        return $this->matrixFactorization->train($tasteMatrix);
    }

    /**
     * Predicted CF score for every candidate product for one user, using
     * the trained model — skips products this user already has a taste
     * score for.
     *
     * @param array<int, float> $userRatings this user's productId => tasteScore
     * @param array $model trained model from train()
     * @param int[] $candidateProductIds every product id worth considering (typically the whole catalog)
     * @return array<int, float> candidateProductId => predicted score
     */
    public function predictForUser(int $userId, array $userRatings, array $model, array $candidateProductIds): array
    {
        $predictions = [];
        foreach ($candidateProductIds as $productId) {
            if (isset($userRatings[$productId])) {
                continue;
            }
            $predictions[$productId] = $this->matrixFactorization->predict($model, $userId, $productId);
        }
        return $predictions;
    }
}
