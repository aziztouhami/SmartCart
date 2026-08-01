<?php

namespace App\Service\Recommendation;

use App\Entity\Product;
use App\Entity\ProductRelation;
use App\Repository\ProductRelationRepository;
use App\Repository\GuestEventRepository;
use App\Repository\InteractionRepository;
use App\Repository\ProductRepository;

/**
 * The offline "brain" behind session-based recommendations. Mines two kinds
 * of co-occurrence — anonymous guest sessions and authenticated users'
 * interaction history — and blends it with content similarity (category,
 * brand, product type, shared feature values) into a single relatedness
 * score per product pair. Meant to run as a scheduled batch job
 * (see RebuildRecommendationsCommand); serving a recommendation is then a
 * single indexed lookup against the precomputed product_relation table.
 */
class RecommendationBuilderService
{
    private const LOOKBACK_DAYS = 90;
    private const GUEST_EVENT_RETENTION_DAYS = 180;
    private const TOP_K_PER_PRODUCT = 12;

    // Behavioral signal strength, keyed by "lowerTier-higherTier".
    // Cart and purchase co-occurrence count for much more than two casual
    // views — they're a far stronger signal of "these belong together".
    private const PAIR_WEIGHTS = [
        '1-1' => 1.0,  // viewed + viewed
        '1-2' => 2.5,  // viewed + added to cart
        '2-2' => 4.0,  // added to cart + added to cart
        '1-3' => 3.5,  // viewed + purchased/rated
        '2-3' => 5.0,  // added to cart + purchased/rated
        '3-3' => 6.0,  // purchased/rated + purchased/rated
    ];

    public function __construct(
        private GuestEventRepository $guestEventRepository,
        private InteractionRepository $interactionRepository,
        private ProductRepository $productRepository,
        private ProductRelationRepository $productRelationRepository,
        private ContentSimilarityService $contentSimilarity,
    ) {}

    /**
     * @return array{groups: int, products: int, pairs: int}
     */
    public function rebuild(): array
    {
        $cutoff = new \DateTimeImmutable(sprintf('-%d days', self::LOOKBACK_DAYS));

        $groups     = $this->collectGroups($cutoff);
        $behavioral = $this->accumulateBehavioral($groups);

        $products = $this->productRepository->findAll();
        $byId = [];
        foreach ($products as $product) {
            $byId[$product->getId()] = $product;
        }

        // Learn how predictive each content signal actually is from real
        // co-occurrence before using it, rather than scoring with fixed
        // hand-picked weights.
        $this->contentSimilarity->train($groups, $products);
        $content = $this->collectContentCandidates($products);

        // "Similar" (substitute, e.g. another phone) is the content-based
        // signal alone — category/brand/type/feature closeness. "Complementary"
        // (goes-with, e.g. phone + case) is the behavioral co-occurrence signal,
        // minus pairs that share a leaf category — those are alternatives to
        // each other, not things bought together, even if they were carted in
        // the same session.
        $complementary = $this->excludeSameCategoryPairs($behavioral, $byId);

        $similarRows = $this->buildTopKRows($content, $products, ProductRelation::TYPE_SIMILAR);
        $complementaryRows = $this->buildTopKRows($complementary, $products, ProductRelation::TYPE_COMPLEMENTARY);
        $rows = array_merge($similarRows, $complementaryRows);

        $this->productRelationRepository->replaceAll($rows);

        $this->guestEventRepository->pruneOlderThan(
            new \DateTimeImmutable(sprintf('-%d days', self::GUEST_EVENT_RETENTION_DAYS))
        );

        return [
            'groups'   => count($groups),
            'products' => count($products),
            'pairs'    => count($rows),
            'contentWeightsLearned' => $this->contentSimilarity->isTrained(),
            'contentWeightsConfidence' => round($this->contentSimilarity->getConfidence(), 3),
            'contentWeights' => $this->contentSimilarity->getWeights(),
        ];
    }

    /**
     * @param array<int, array<int, float>> $pairs
     * @param array<int, Product> $byId
     * @return array<int, array<int, float>>
     */
    private function excludeSameCategoryPairs(array $pairs, array $byId): array
    {
        $filtered = [];
        foreach ($pairs as $lo => $highs) {
            foreach ($highs as $hi => $score) {
                $a = $byId[$lo] ?? null;
                $b = $byId[$hi] ?? null;
                if ($a && $b && $a->getCategory()?->getId() === $b->getCategory()?->getId()) {
                    continue;
                }
                $filtered[$lo][$hi] = $score;
            }
        }

        return $filtered;
    }

    /**
     * Every guest session and every authenticated user's interaction
     * history, each reduced to [productId => strongest tier reached].
     * Both are "a set of products one identity engaged with together" —
     * the unit co-occurrence is mined from.
     *
     * @return array<int, array<int, int>>
     */
    private function collectGroups(\DateTimeImmutable $since): array
    {
        $groups = [];

        foreach ($this->guestEventRepository->groupedSessionsSince($since) as $productTypes) {
            $tiers = $this->reduceToTiers($productTypes);
            if (count($tiers) > 1) {
                $groups[] = $tiers;
            }
        }

        foreach ($this->interactionRepository->groupedByUserSince($since) as $productTypes) {
            $tiers = $this->reduceToTiers($productTypes);
            if (count($tiers) > 1) {
                $groups[] = $tiers;
            }
        }

        return $groups;
    }

    /**
     * @param array<int, string> $productTypes productId => event/interaction type
     * @return array<int, int> productId => strongest tier
     */
    private function reduceToTiers(array $productTypes): array
    {
        $tiers = [];
        foreach ($productTypes as $productId => $type) {
            $tiers[$productId] = max($tiers[$productId] ?? 0, $this->tier($type));
        }
        return $tiers;
    }

    private function tier(string $type): int
    {
        return match ($type) {
            'cart' => 2,
            'purchase', 'rating' => 3,
            default => 1, // view
        };
    }

    private function pairWeight(int $tierA, int $tierB): float
    {
        $key = $tierA <= $tierB ? "{$tierA}-{$tierB}" : "{$tierB}-{$tierA}";
        return self::PAIR_WEIGHTS[$key] ?? 1.0;
    }

    /**
     * @param array<int, array<int, int>> $groups
     * @return array<int, array<int, float>> [lowerProductId][higherProductId] => score
     */
    private function accumulateBehavioral(array $groups): array
    {
        $behavioral = [];

        foreach ($groups as $tiers) {
            $productIds = array_keys($tiers);
            $count = count($productIds);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = $productIds[$i];
                    $b = $productIds[$j];
                    [$lo, $hi] = $a < $b ? [$a, $b] : [$b, $a];
                    $weight = $this->pairWeight($tiers[$a], $tiers[$b]);
                    $behavioral[$lo][$hi] = ($behavioral[$lo][$hi] ?? 0) + $weight;
                }
            }
        }

        return $behavioral;
    }

    /**
     * Candidate pairs drawn from products sharing a category, brand, or
     * type — scoring every possible pair (O(n^2) over the whole catalog)
     * isn't necessary since unrelated products score zero anyway; bucketing
     * keeps this proportional to cluster sizes instead of catalog size.
     *
     * @param Product[] $products
     * @return array<int, array<int, float>> [lowerProductId][higherProductId] => score
     */
    private function collectContentCandidates(array $products): array
    {
        $buckets = $this->contentSimilarity->bucketByShared($products);

        $content = [];
        $scoreBucket = function (array $bucket) use (&$content) {
            $count = count($bucket);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = $bucket[$i];
                    $b = $bucket[$j];
                    $aId = $a->getId();
                    $bId = $b->getId();
                    [$lo, $hi] = $aId < $bId ? [$aId, $bId] : [$bId, $aId];
                    if (isset($content[$lo][$hi])) {
                        continue; // already fully scored via another shared bucket
                    }
                    $content[$lo][$hi] = $this->contentSimilarity->score($a, $b);
                }
            }
        };

        foreach ($buckets['category'] as $bucket) {
            $scoreBucket($bucket);
        }
        foreach ($buckets['parentCategory'] as $bucket) {
            $scoreBucket($bucket);
        }
        foreach ($buckets['brand'] as $bucket) {
            $scoreBucket($bucket);
        }
        foreach ($buckets['type'] as $bucket) {
            $scoreBucket($bucket);
        }

        return $content;
    }

    /**
     * Mirrors every scored pair into both directions and keeps only each
     * product's top-K strongest relations — the table stays small and the
     * serving lookup stays a flat indexed scan. Every product gets at least
     * a handful of rows, even ones with zero category/brand/type/behavioral
     * peers (e.g. the sole product in its category) — those fall back to
     * generic trending picks instead of being left with no relations at all
     * and so never being recommendable to anyone.
     *
     * @param array<int, array<int, float>> $merged
     * @param Product[] $products
     * @return array<int, array{productId: int, relatedProductId: int, score: float, type: string}>
     */
    private function buildTopKRows(array $merged, array $products, string $type): array
    {
        $byProduct = [];
        foreach ($merged as $lo => $highs) {
            foreach ($highs as $hi => $score) {
                if ($score <= 0) {
                    continue;
                }
                $byProduct[$lo][$hi] = $score;
                $byProduct[$hi][$lo] = $score;
            }
        }

        $trending = $products;
        usort($trending, fn ($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
        $trendingIds = array_map(fn ($p) => $p->getId(), $trending);

        $rows = [];
        foreach ($products as $product) {
            $productId = $product->getId();
            $related = $byProduct[$productId] ?? [];

            if (empty($related)) {
                foreach ($trendingIds as $candidateId) {
                    if ($candidateId === $productId) {
                        continue;
                    }
                    $related[$candidateId] = 0.5; // low-confidence fallback, well below any real signal
                    if (count($related) >= 5) {
                        break;
                    }
                }
            }

            arsort($related);
            $top = array_slice($related, 0, self::TOP_K_PER_PRODUCT, true);
            foreach ($top as $relatedId => $score) {
                $rows[] = [
                    'productId' => $productId,
                    'relatedProductId' => $relatedId,
                    'score' => round($score, 4),
                    'type' => $type,
                ];
            }
        }

        return $rows;
    }
}
