<?php

namespace App\ML;

/**
 * Latent-factor matrix factorization (funk-SVD style, trained by stochastic
 * gradient descent) — the "learned" replacement for plain item-item cosine
 * similarity. Each user and each product gets a small vector of learned
 * factors; a predicted taste score is the dot product of the two plus bias
 * terms. Unlike cosine similarity (which can only ever point at products
 * literally similar to something already rated), a trained model can score
 * *any* product for a user directly — including ones with no obvious
 * behavioral overlap, which is what makes collaborative filtering capable
 * of surprising, non-obvious recommendations.
 *
 * Pure PHP, no extension required — at this catalog's scale (handfuls of
 * users/products) training runs in well under a second.
 */
class MatrixFactorizationTrainer
{
    private const FACTORS = 8;
    private const EPOCHS = 150;
    private const LEARNING_RATE = 0.01;
    private const REGULARIZATION = 0.05;

    /**
     * @param array<int, array<int, float>> $matrix userId => [productId => tasteScore]
     *
     * @return array{mu: float, userFactors: array<int, float[]>, itemFactors: array<int, float[]>, userBias: array<int, float>, itemBias: array<int, float>}
     */
    public function train(array $matrix): array
    {
        $triples = [];
        $sum = 0.0;
        foreach ($matrix as $userId => $ratings) {
            foreach ($ratings as $productId => $score) {
                $triples[] = [$userId, $productId, $score];
                $sum += $score;
            }
        }

        $n = count($triples);
        if (0 === $n) {
            return ['mu' => 0.0, 'userFactors' => [], 'itemFactors' => [], 'userBias' => [], 'itemBias' => []];
        }

        $mu = $sum / $n;
        $userFactors = [];
        $itemFactors = [];
        $userBias = [];
        $itemBias = [];

        foreach ($triples as [$userId, $productId, $_]) {
            $userFactors[$userId] ??= $this->randomFactorVector();
            $itemFactors[$productId] ??= $this->randomFactorVector();
            $userBias[$userId] ??= 0.0;
            $itemBias[$productId] ??= 0.0;
        }

        for ($epoch = 0; $epoch < self::EPOCHS; ++$epoch) {
            shuffle($triples); // re-ordering each epoch helps SGD converge more evenly

            foreach ($triples as [$userId, $productId, $actual]) {
                $uVec = $userFactors[$userId];
                $vVec = $itemFactors[$productId];

                $prediction = $mu + $userBias[$userId] + $itemBias[$productId];
                for ($k = 0; $k < self::FACTORS; ++$k) {
                    $prediction += $uVec[$k] * $vVec[$k];
                }
                $error = $actual - $prediction;

                $userBias[$userId] += self::LEARNING_RATE * ($error - self::REGULARIZATION * $userBias[$userId]);
                $itemBias[$productId] += self::LEARNING_RATE * ($error - self::REGULARIZATION * $itemBias[$productId]);

                for ($k = 0; $k < self::FACTORS; ++$k) {
                    $uk = $uVec[$k];
                    $vk = $vVec[$k];
                    $userFactors[$userId][$k] = $uk + self::LEARNING_RATE * ($error * $vk - self::REGULARIZATION * $uk);
                    $itemFactors[$productId][$k] = $vk + self::LEARNING_RATE * ($error * $uk - self::REGULARIZATION * $vk);
                }
            }
        }

        return [
            'mu' => $mu,
            'userFactors' => $userFactors,
            'itemFactors' => $itemFactors,
            'userBias' => $userBias,
            'itemBias' => $itemBias,
        ];
    }

    /**
     * Predicted taste score for a user/product pair. Users or products the
     * model never saw default to the global mean — a new user with no
     * history simply gets no opinion from this engine, which is correct
     * (Engine B and the preference fallback are what cover that case).
     *
     * @param array{mu: float, userFactors: array, itemFactors: array, userBias: array, itemBias: array} $model
     */
    public function predict(array $model, int $userId, int $productId): float
    {
        $prediction = $model['mu'] + ($model['userBias'][$userId] ?? 0.0) + ($model['itemBias'][$productId] ?? 0.0);

        $uVec = $model['userFactors'][$userId] ?? null;
        $vVec = $model['itemFactors'][$productId] ?? null;
        if (null !== $uVec && null !== $vVec) {
            foreach ($uVec as $k => $value) {
                $prediction += $value * $vVec[$k];
            }
        }

        return $prediction;
    }

    /**
     * @return float[]
     */
    private function randomFactorVector(): array
    {
        $vector = [];
        for ($k = 0; $k < self::FACTORS; ++$k) {
            $vector[] = mt_rand(-100, 100) / 1000; // small values around 0, e.g. [-0.1, 0.1]
        }

        return $vector;
    }
}
