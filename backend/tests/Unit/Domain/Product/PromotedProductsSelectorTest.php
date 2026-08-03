<?php

namespace App\Tests\Unit\Domain\Product;

use App\Domain\Product\PromotedProductsSelector;
use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\PromotionRepository;
use PHPUnit\Framework\TestCase;

class PromotedProductsSelectorTest extends TestCase
{
    private function makeProduct(int $id): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);

        return $product;
    }

    public function testReturnsEmptyWithoutLoadingCatalogWhenNoActivePromotions(): void
    {
        $productRepository = $this->createMock(ProductRepository::class);
        $promotionRepository = $this->createMock(PromotionRepository::class);

        $promotionRepository->method('findActive')->willReturn([]);
        $productRepository->expects($this->never())->method('findAllWithRelations');

        $selector = new PromotedProductsSelector($productRepository, $promotionRepository);
        $result = $selector->select(20);

        $this->assertSame([], $result['products']);
        $this->assertSame([], $result['promoMap']);
    }

    public function testSelectsAndCapsPromotedProductsNewestFirst(): void
    {
        $productRepository = $this->createMock(ProductRepository::class);
        $promotionRepository = $this->createMock(PromotionRepository::class);

        $p1 = $this->makeProduct(1);
        $p2 = $this->makeProduct(2);
        $p3 = $this->makeProduct(3);

        $promotionRepository->method('findActive')->willReturn(['some-active-promo']);
        $productRepository->method('findAllWithRelations')->willReturn([$p1, $p2, $p3]);
        $promotionRepository->method('findActiveForProducts')->willReturn([1 => 'promoA', 3 => 'promoC']);

        $selector = new PromotedProductsSelector($productRepository, $promotionRepository);
        $result = $selector->select(1);

        $this->assertCount(1, $result['products']);
        $this->assertSame(3, $result['products'][0]->getId());
    }
}
