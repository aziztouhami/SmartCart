<?php

namespace App\Tests\Unit\Domain\Product;

use App\Domain\Product\BestSellersResolver;
use App\Entity\Product;
use App\Repository\ProductRepository;
use PHPUnit\Framework\TestCase;

class BestSellersResolverTest extends TestCase
{
    public function testReturnsTopSellingProductsWhenAvailable(): void
    {
        $productRepository = $this->createMock(ProductRepository::class);
        $products = [$this->createMock(Product::class)];

        $productRepository->method('getTopSelling')->with(10)->willReturn($products);
        $productRepository->expects($this->never())->method('findWithFilters');

        $resolver = new BestSellersResolver($productRepository);

        $this->assertSame($products, $resolver->resolve(10));
    }

    public function testFallsBackToNewestProductsWhenNoSalesHistory(): void
    {
        $productRepository = $this->createMock(ProductRepository::class);
        $fallback = [$this->createMock(Product::class)];

        $productRepository->method('getTopSelling')->willReturn([]);
        $productRepository->expects($this->once())
            ->method('findWithFilters')
            ->willReturn($fallback);

        $resolver = new BestSellersResolver($productRepository);

        $this->assertSame($fallback, $resolver->resolve(10));
    }
}
