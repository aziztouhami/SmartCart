<?php

namespace App\Tests\Unit\Recommendation;

use App\Entity\Category;
use App\Entity\Product;
use App\Repository\PromotionRepository;
use App\Service\Recommendation\RecommendationBusinessRules;
use App\Service\Recommendation\SeasonalBoostService;
use PHPUnit\Framework\TestCase;

class RecommendationBusinessRulesTest extends TestCase
{
    private PromotionRepository $promotionRepository;
    private SeasonalBoostService $seasonalBoost;
    private RecommendationBusinessRules $rules;

    protected function setUp(): void
    {
        $this->promotionRepository = $this->createMock(PromotionRepository::class);
        $this->seasonalBoost = $this->createMock(SeasonalBoostService::class);
        $this->seasonalBoost->method('isInSeason')->willReturn(false);

        $this->rules = new RecommendationBusinessRules($this->promotionRepository, $this->seasonalBoost);
    }

    private function makeProduct(int $id, int $stock = 5, ?Category $category = null): Product
    {
        $product = new Product();
        $ref = new \ReflectionProperty(Product::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($product, $id);
        $product->setStock($stock);
        $product->setCreatedAt(new \DateTimeImmutable('-30 days')); // not a new arrival by default
        if ($category) {
            $product->setCategory($category);
        }

        return $product;
    }

    private function makeCategory(int $id): Category
    {
        $category = new Category();
        $ref = new \ReflectionProperty(Category::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($category, $id);

        return $category;
    }

    public function testReturnsEmptyForEmptyCandidates(): void
    {
        $this->promotionRepository->expects($this->never())->method('findActiveForProducts');

        $result = $this->rules->apply([], []);

        $this->assertSame([], $result);
    }

    public function testBoostsPromotedProducts(): void
    {
        $product = $this->makeProduct(1);
        $promoMap = [1 => true];

        $result = $this->rules->apply([1 => 1.0], [1 => $product], $promoMap);

        $this->assertSame(2.0, $result[1]); // +BOOST_PROMOTION (1.0)
    }

    public function testBoostsNewArrivals(): void
    {
        $product = $this->makeProduct(1);
        $product->setCreatedAt(new \DateTimeImmutable('-1 day'));

        $result = $this->rules->apply([1 => 1.0], [1 => $product], []);

        $this->assertSame(1.5, $result[1]); // +BOOST_NEW_ARRIVAL (0.5)
    }

    public function testBoostsSeasonalProducts(): void
    {
        $product = $this->makeProduct(1);
        $this->seasonalBoost = $this->createMock(SeasonalBoostService::class);
        $this->seasonalBoost->method('isInSeason')->willReturn(true);
        $rules = new RecommendationBusinessRules($this->promotionRepository, $this->seasonalBoost);

        $result = $rules->apply([1 => 1.0], [1 => $product], []);

        $this->assertSame(1.5, $result[1]); // +BOOST_SEASONAL (0.5)
    }

    public function testExcludesOutOfStockProducts(): void
    {
        $product = $this->makeProduct(1, stock: 0);

        $result = $this->rules->apply([1 => 1.0], [1 => $product], []);

        $this->assertSame([], $result);
    }

    public function testExcludesCandidatesMissingFromProductsById(): void
    {
        $result = $this->rules->apply([1 => 1.0], [], []);

        $this->assertSame([], $result);
    }

    public function testCapsResultsPerCategoryForDiversity(): void
    {
        $category = $this->makeCategory(100);
        $candidates = [];
        $productsById = [];
        foreach ([1, 2, 3, 4] as $id) {
            $productsById[$id] = $this->makeProduct($id, category: $category);
            $candidates[$id] = 5 - $id; // descending scores: 1 > 2 > 3 > 4
        }

        $result = $this->rules->apply($candidates, $productsById, []);

        // Diversity cap is 3 per category — the lowest-scored of the four
        // (id 4) is dropped even though it was otherwise a valid candidate.
        $this->assertCount(3, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
        $this->assertArrayHasKey(3, $result);
        $this->assertArrayNotHasKey(4, $result);
    }

    public function testRespectsTopKLimit(): void
    {
        $candidates = [];
        $productsById = [];
        foreach ([1, 2, 3] as $id) {
            $productsById[$id] = $this->makeProduct($id, category: $this->makeCategory($id)); // distinct categories, diversity cap doesn't interfere
            $candidates[$id] = (float) $id;
        }

        $result = $this->rules->apply($candidates, $productsById, [], topK: 2);

        $this->assertCount(2, $result);
    }

    public function testComputesPromoMapInternallyWhenNotProvided(): void
    {
        $product = $this->makeProduct(1);

        $this->promotionRepository->expects($this->once())
            ->method('findActiveForProducts')
            ->with([1 => $product])
            ->willReturn([1 => true]);

        $result = $this->rules->apply([1 => 1.0], [1 => $product]);

        $this->assertSame(2.0, $result[1]);
    }

    public function testUsesProvidedPromoMapWithoutRecomputing(): void
    {
        $product = $this->makeProduct(1);

        $this->promotionRepository->expects($this->never())->method('findActiveForProducts');

        $result = $this->rules->apply([1 => 1.0], [1 => $product], [1 => true]);

        $this->assertSame(2.0, $result[1]);
    }
}
