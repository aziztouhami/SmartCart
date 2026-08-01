<?php

namespace App\ML;

/**
 * A small, dependency-free logistic regression trainer (batch gradient
 * descent with L2 regularization). This is the actual "learning" step
 * behind the recommender's content weights: instead of someone guessing
 * that a matching category is worth 3 points, the weights are fit to
 * predict real co-occurrence from the catalog's own products.
 *
 * Deliberately not a generic ML library — just enough gradient descent to
 * fit a handful of binary content features, in pure PHP, no extension
 * required. At catalog scale here (tens of products, low hundreds of
 * training pairs) this trains in milliseconds.
 */
class LogisticRegressionTrainer
{
    private const LEARNING_RATE = 0.3;
    private const L2_REGULARIZATION = 0.01;
    private const EPOCHS = 400;

    /**
     * @param array<int, float[]> $features one row per example, each a fixed-length feature vector
     * @param array<int, float> $labels 1.0 or 0.0, same length as $features
     * @param array<int, float> $sampleWeights how strongly each example should pull the fit (e.g. co-occurrence strength); defaults to 1.0
     * @return float[] learned weights, [bias, w1, w2, ..., wN]
     */
    public function train(array $features, array $labels, array $sampleWeights = []): array
    {
        $n = count($features);
        if ($n === 0) {
            return [];
        }
        $dimensions = count($features[0]);
        $weights = array_fill(0, $dimensions + 1, 0.0); // index 0 = bias

        for ($epoch = 0; $epoch < self::EPOCHS; $epoch++) {
            $gradients = array_fill(0, $dimensions + 1, 0.0);

            for ($i = 0; $i < $n; $i++) {
                $x = $features[$i];
                $prediction = $this->predict($x, $weights);
                $error = $prediction - $labels[$i];
                $sampleWeight = $sampleWeights[$i] ?? 1.0;

                $gradients[0] += $error * $sampleWeight;
                for ($d = 0; $d < $dimensions; $d++) {
                    $gradients[$d + 1] += $error * $sampleWeight * $x[$d];
                }
            }

            $weights[0] -= self::LEARNING_RATE * ($gradients[0] / $n);
            for ($d = 0; $d < $dimensions; $d++) {
                $reg = self::L2_REGULARIZATION * $weights[$d + 1];
                $weights[$d + 1] -= self::LEARNING_RATE * (($gradients[$d + 1] / $n) + $reg);
            }
        }

        return $weights;
    }

    /**
     * @param float[] $features
     * @param float[] $weights [bias, w1, ..., wN]
     */
    public function predict(array $features, array $weights): float
    {
        $z = $weights[0];
        foreach ($features as $i => $value) {
            $z += $weights[$i + 1] * $value;
        }
        return $this->sigmoid($z);
    }

    private function sigmoid(float $z): float
    {
        // Clamp to avoid float overflow on extreme inputs.
        $z = max(-35.0, min(35.0, $z));
        return 1.0 / (1.0 + exp(-$z));
    }
}
