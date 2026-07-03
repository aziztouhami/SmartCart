<?php

namespace App\Tests\Unit\Recommendation;

use App\Entity\Category;
use App\Entity\Product;
use App\Recommendation\Service\SeasonalBoostService;
use PHPUnit\Framework\TestCase;

class SeasonalBoostServiceTest extends TestCase
{
    private SeasonalBoostService $service;

    protected function setUp(): void
    {
        $this->service = new SeasonalBoostService();
    }

    private function productInCategory(?array $seasonalMonths): Product
    {
        $category = new Category();
        $category->setSeasonalMonths($seasonalMonths);

        $product = new Product();
        $product->setCategory($category);

        return $product;
    }

    public function testReturnsFalseWhenCategoryHasNoSeasonalMonths(): void
    {
        $product = $this->productInCategory(null);

        $this->assertFalse($this->service->isInSeason($product, new \DateTimeImmutable('2026-06-15')));
    }

    public function testReturnsFalseWhenSeasonalMonthsEmpty(): void
    {
        $product = $this->productInCategory([]);

        $this->assertFalse($this->service->isInSeason($product, new \DateTimeImmutable('2026-06-15')));
    }

    public function testReturnsTrueWhenCurrentMonthIsInSeasonalMonths(): void
    {
        $product = $this->productInCategory([6, 7, 8]);

        $this->assertTrue($this->service->isInSeason($product, new \DateTimeImmutable('2026-06-15')));
    }

    public function testReturnsFalseWhenCurrentMonthIsNotInSeasonalMonths(): void
    {
        $product = $this->productInCategory([12, 1, 2]);

        $this->assertFalse($this->service->isInSeason($product, new \DateTimeImmutable('2026-06-15')));
    }

    public function testReturnsFalseWhenProductHasNoCategory(): void
    {
        $product = new Product();

        $this->assertFalse($this->service->isInSeason($product, new \DateTimeImmutable('2026-06-15')));
    }
}
