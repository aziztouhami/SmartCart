<?php

namespace App\Recommendation\Service;

use App\Entity\Product;
use App\Recommendation\Ml\LogisticRegressionTrainer;

/**
 * Shared "how alike are these two products" scoring — category, brand,
 * product type, and shared feature values. Used by both the guest
 * item-relation batch job and the per-user content-based engine, so the
 * notion of "similar product" is consistent across the whole recommender.
 *
 * The weight given to each signal isn't hand-picked — train() fits a small
 * logistic regression against real co-occurrence data (pairs of products
 * that actually got viewed/carted/bought together vs. random pairs that
 * didn't), so "does matching brand actually predict that two products
 * belong together in *this* catalog" is answered from data, not guessed.
 * Falls back to sensible defaults until there's enough behavioral data to
 * learn from reliably.
 */
class ContentSimilarityService
{
    private const MIN_POSITIVE_PAIRS_TO_TRAIN = 8;
    // Below this many positive examples, the fit is still mostly noise (a
    // handful of co-occurring pairs can't reliably separate correlated
    // signals like "same category" from "same type"). Confidence ramps
    // linearly from 0 at MIN_POSITIVE_PAIRS_TO_TRAIN up to 1 here, so the
    // learned weights are blended in gradually rather than swapped in
    // wholesale the moment the minimum is crossed.
    private const FULL_CONFIDENCE_PAIRS = 120;
    private const MAX_PAIR_SAMPLE_WEIGHT = 3.0;
    private const NEGATIVE_SAMPLE_ATTEMPTS_PER_NEEDED = 20;

    // Used until enough real co-occurrence data exists to learn from —
    // reasonable starting assumptions, not the final word.
    private const DEFAULT_WEIGHTS = [
        'category' => 3.0,
        'parentCategory' => 1.0,
        'brand' => 1.5,
        'type' => 2.0,
        'featureMatch' => 4.0,
    ];

    private array $weights = self::DEFAULT_WEIGHTS;
    private bool $trained = false;
    private float $confidence = 0.0;

    public function __construct(
        private LogisticRegressionTrainer $trainer,
    ) {}

    /**
     * @param array<int, array<int, int>> $groups behavioral co-occurrence groups
     *        (same shape RecommendationBuilderService::collectGroups produces)
     * @param Product[] $products
     */
    public function train(array $groups, array $products): void
    {
        $byId = [];
        foreach ($products as $product) {
            $byId[$product->getId()] = $product;
        }

        [$features, $labels, $sampleWeights] = $this->buildTrainingSet($groups, $byId);

        $positiveCount = count(array_filter($labels, fn ($l) => $l > 0.5));
        if ($positiveCount < self::MIN_POSITIVE_PAIRS_TO_TRAIN) {
            $this->weights = self::DEFAULT_WEIGHTS;
            $this->trained = false;
            return;
        }

        $learned = $this->trainer->train($features, $labels, $sampleWeights);
        if (empty($learned)) {
            return;
        }

        // learned = [bias, category, parentCategory, brand, type, featureMatch].
        // The bias is dropped from scoring (it's a uniform offset and would
        // break "zero score = zero signal" elsewhere); negative weights are
        // clamped to zero since a feature should never *subtract* relatedness
        // in this additive scoring scheme, just contribute nothing useful.
        $learnedWeights = [
            'category' => max(0.0, $learned[1]),
            'parentCategory' => max(0.0, $learned[2]),
            'brand' => max(0.0, $learned[3]),
            'type' => max(0.0, $learned[4]),
            'featureMatch' => max(0.0, $learned[5]),
        ];

        // Shrink toward the defaults proportionally to how much data this was
        // actually fit on — a handful of co-occurring pairs (e.g. one noisy
        // browsing session) shouldn't be able to zero out a signal as useful
        // as "same brand" just because that session happened not to touch it.
        // As more real behavior accumulates, confidence rises and the learned
        // values take over.
        $confidence = min(1.0, ($positiveCount - self::MIN_POSITIVE_PAIRS_TO_TRAIN)
            / (self::FULL_CONFIDENCE_PAIRS - self::MIN_POSITIVE_PAIRS_TO_TRAIN));

        $this->weights = [];
        foreach (self::DEFAULT_WEIGHTS as $key => $default) {
            $this->weights[$key] = (1 - $confidence) * $default + $confidence * $learnedWeights[$key];
        }
        $this->confidence = $confidence;
        $this->trained = true;
    }

    public function isTrained(): bool
    {
        return $this->trained;
    }

    /**
     * How much the current weights lean on the learned fit vs. the
     * defaults — 0 means pure defaults, 1 means the data fully overrode them.
     */
    public function getConfidence(): float
    {
        return $this->confidence;
    }

    /**
     * @return array{category: float, parentCategory: float, brand: float, type: float, featureMatch: float}
     */
    public function getWeights(): array
    {
        return $this->weights;
    }

    public function score(Product $a, Product $b): float
    {
        [$sameLeaf, $sameParentDiffLeaf, $sameBrand, $sameType, $featureMatch] = $this->extractFeatures($a, $b);

        return $this->weights['category'] * $sameLeaf
            + $this->weights['parentCategory'] * $sameParentDiffLeaf
            + $this->weights['brand'] * $sameBrand
            + $this->weights['type'] * $sameType
            + $this->weights['featureMatch'] * ($sameType > 0.5 ? $featureMatch : 0.0);
    }

    /**
     * Share of comparable feature values (present on both products) that
     * actually match — e.g. two smartphones both specifying Color match on
     * "Black" but differ on Storage contributes 1/2 to the ratio.
     */
    public function featureMatchRatio(array $attributesA, array $attributesB): float
    {
        $considered = 0;
        $matches = 0;

        foreach (array_keys($attributesA) as $slug) {
            if (!array_key_exists($slug, $attributesB)) {
                continue;
            }
            $considered++;
            if ($attributesA[$slug] === $attributesB[$slug]) {
                $matches++;
            }
        }

        return $considered > 0 ? $matches / $considered : 0.0;
    }

    /**
     * Buckets products by category/parent category/brand/type so candidate
     * pair generation stays proportional to cluster sizes instead of O(n^2)
     * over the whole catalog — unrelated products never score above zero
     * anyway. The parent-category bucket is what lets a product with no
     * leaf-category or type peers (e.g. the only product in "Home Decor")
     * still surface candidates from sibling categories under the same
     * parent (e.g. "Furniture") instead of having none at all.
     *
     * @param Product[] $products
     * @return array{category: array<int, Product[]>, parentCategory: array<int, Product[]>, brand: array<int, Product[]>, type: array<int, Product[]>}
     */
    public function bucketByShared(array $products): array
    {
        $byCategory = [];
        $byParentCategory = [];
        $byBrand = [];
        $byType = [];

        foreach ($products as $product) {
            if ($catId = $product->getCategory()?->getId()) {
                $byCategory[$catId][] = $product;
            }
            $parentId = $product->getCategory()?->getParent()?->getId();
            if ($parentId) {
                $byParentCategory[$parentId][] = $product;
            }
            if ($brandId = $product->getBrand()?->getId()) {
                $byBrand[$brandId][] = $product;
            }
            if ($typeId = $product->getProductType()?->getId()) {
                $byType[$typeId][] = $product;
            }
        }

        return [
            'category' => $byCategory,
            'parentCategory' => $byParentCategory,
            'brand' => $byBrand,
            'type' => $byType,
        ];
    }

    /**
     * @return float[] [sameLeafCategory, sameParentButDifferentLeaf, sameBrand, sameType, featureMatchRatio]
     */
    private function extractFeatures(Product $a, Product $b): array
    {
        $categoryA = $a->getCategory();
        $categoryB = $b->getCategory();
        $sameLeaf = $categoryA?->getId() === $categoryB?->getId();

        $parentA = $categoryA?->getParent()?->getId() ?? $categoryA?->getId();
        $parentB = $categoryB?->getParent()?->getId() ?? $categoryB?->getId();
        $sameParentDiffLeaf = !$sameLeaf && $parentA !== null && $parentA === $parentB;

        $sameBrand = $a->getBrand() && $b->getBrand() && $a->getBrand()->getId() === $b->getBrand()->getId();
        $sameType = $a->getProductType() && $b->getProductType()
            && $a->getProductType()->getId() === $b->getProductType()->getId();

        $featureMatch = $sameType ? $this->featureMatchRatio($a->getAttributes(), $b->getAttributes()) : 0.0;

        return [
            $sameLeaf ? 1.0 : 0.0,
            $sameParentDiffLeaf ? 1.0 : 0.0,
            $sameBrand ? 1.0 : 0.0,
            $sameType ? 1.0 : 0.0,
            $featureMatch,
        ];
    }

    /**
     * @param array<int, array<int, int>> $groups
     * @param array<int, Product> $byId
     * @return array{0: array<int, float[]>, 1: array<int, float>, 2: array<int, float>}
     */
    private function buildTrainingSet(array $groups, array $byId): array
    {
        $positivePairs = [];
        foreach ($groups as $tiers) {
            $ids = array_keys($tiers);
            $count = count($ids);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = $ids[$i];
                    $b = $ids[$j];
                    [$lo, $hi] = $a < $b ? [$a, $b] : [$b, $a];
                    $positivePairs[$lo][$hi] = ($positivePairs[$lo][$hi] ?? 0) + 1;
                }
            }
        }

        $features = [];
        $labels = [];
        $sampleWeights = [];
        $usedPairs = [];

        foreach ($positivePairs as $lo => $highs) {
            foreach ($highs as $hi => $count) {
                if (!isset($byId[$lo], $byId[$hi])) {
                    continue;
                }
                $features[] = $this->extractFeatures($byId[$lo], $byId[$hi]);
                $labels[] = 1.0;
                $sampleWeights[] = min(self::MAX_PAIR_SAMPLE_WEIGHT, (float) $count);
                $usedPairs["{$lo}-{$hi}"] = true;
            }
        }

        $positiveCount = count($features);
        $ids = array_keys($byId);
        $idCount = count($ids);

        if ($idCount >= 2) {
            $needed = max($positiveCount, 10);
            $maxAttempts = $needed * self::NEGATIVE_SAMPLE_ATTEMPTS_PER_NEEDED;
            $added = 0;
            $attempts = 0;

            while ($added < $needed && $attempts < $maxAttempts) {
                $attempts++;
                $a = $ids[array_rand($ids)];
                $b = $ids[array_rand($ids)];
                if ($a === $b) {
                    continue;
                }
                [$lo, $hi] = $a < $b ? [$a, $b] : [$b, $a];
                $key = "{$lo}-{$hi}";
                if (isset($usedPairs[$key])) {
                    continue;
                }
                $usedPairs[$key] = true;

                $features[] = $this->extractFeatures($byId[$lo], $byId[$hi]);
                $labels[] = 0.0;
                $sampleWeights[] = 1.0;
                $added++;
            }
        }

        return [$features, $labels, $sampleWeights];
    }
}
