<?php

namespace App\Tests\Unit\Recommendation;

use App\Service\Recommendation\CachedCollaborativeFilteringModel;
use App\Service\Recommendation\CollaborativeFilteringService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CachedCollaborativeFilteringModelTest extends TestCase
{
    private CacheInterface $cache;
    private CollaborativeFilteringService $collaborativeFiltering;
    private CachedCollaborativeFilteringModel $cachedModel;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->collaborativeFiltering = $this->createMock(CollaborativeFilteringService::class);

        $this->cachedModel = new CachedCollaborativeFilteringModel($this->cache, $this->collaborativeFiltering);
    }

    /** Mimics Symfony's real cache: a miss invokes the callback and returns its result. */
    private function stubCacheMiss(): void
    {
        $this->cache->method('get')->willReturnCallback(function (string $key, callable $callback) {
            return $callback($this->createMock(ItemInterface::class));
        });
    }

    public function testGetTrainsAndCachesOnMiss(): void
    {
        $this->stubCacheMiss();

        $tasteMatrix = [1 => [2 => 3.0]];
        $trainedModel = ['factors' => 'trained'];
        $this->collaborativeFiltering->expects($this->once())->method('buildTasteMatrix')->willReturn($tasteMatrix);
        $this->collaborativeFiltering->expects($this->once())->method('train')->with($tasteMatrix)->willReturn($trainedModel);

        $result = $this->cachedModel->get();

        $this->assertSame($trainedModel, $result);
    }

    public function testRefreshTrainsOnTheGivenMatrixWithoutRebuildingIt(): void
    {
        $this->stubCacheMiss();

        $tasteMatrix = [1 => [2 => 3.0]];
        $trainedModel = ['factors' => 'fresh'];
        // The caller already has the matrix (it needs it for other things
        // too) — refresh() must not build it again.
        $this->collaborativeFiltering->expects($this->never())->method('buildTasteMatrix');
        $this->collaborativeFiltering->expects($this->once())->method('train')->with($tasteMatrix)->willReturn($trainedModel);

        $this->cache->expects($this->once())->method('delete');

        $result = $this->cachedModel->refresh($tasteMatrix);

        $this->assertSame($trainedModel, $result);
    }
}
