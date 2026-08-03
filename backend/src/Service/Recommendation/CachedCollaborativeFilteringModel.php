<?php

namespace App\Service\Recommendation;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Caches the trained CF model so the live recommendation path
 * (RecommendationServingService) can use collaborative filtering without
 * paying the training cost on every request. Training
 * (CollaborativeFilteringService::train()) iterates the full user-item
 * taste matrix over several epochs — fine for a batch job, far too slow
 * for a request; predicting against an already-trained model is cheap (a
 * handful of dot products), so that's what runs live.
 *
 * Refreshed explicitly by the batch job (app:rebuild-recommendations)
 * right after training, so a rebuild is reflected in live results
 * immediately. Falls back to training on the spot on a cache miss (cold
 * cache, or the batch job has genuinely never run) so live recommendations
 * are never blocked on the batch job having run first — that request just
 * pays the one-time training cost.
 */
class CachedCollaborativeFilteringModel
{
    private const CACHE_KEY = 'recommendation.cf_model.v1';
    private const CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private CacheInterface $cache,
        private CollaborativeFilteringService $collaborativeFiltering,
    ) {
    }

    /**
     * @return array the trained model, opaque — pass straight to CollaborativeFilteringService::predictForUser()
     */
    public function get(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);
            $tasteMatrix = $this->collaborativeFiltering->buildTasteMatrix();

            return $this->collaborativeFiltering->train($tasteMatrix);
        });
    }

    /**
     * Trains on the given (already-built) taste matrix and overwrites the
     * cached model immediately — called by the batch job, which needs that
     * same matrix itself for other purposes, so it's passed in here rather
     * than rebuilt a second time. Reflected in live results right away
     * instead of waiting for the TTL to expire.
     *
     * @param array<int, array<int, float>> $tasteMatrix userId => [productId => tasteScore]
     *
     * @return array the freshly trained model
     */
    public function refresh(array $tasteMatrix): array
    {
        $model = $this->collaborativeFiltering->train($tasteMatrix);

        $this->cache->delete(self::CACHE_KEY);
        $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) use ($model) {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);

            return $model;
        });

        return $model;
    }
}
