<?php

namespace App\Domain\Product;

use App\Repository\ProductRepository;

/**
 * Best-selling products by total units sold, falling back to the newest
 * products when there's no order history yet — the rule behind
 * `GET /api/products/best-sellers`.
 */
class BestSellersResolver
{
    public function __construct(
        private ProductRepository $productRepository,
    ) {
    }

    public function resolve(int $limit): array
    {
        $products = $this->productRepository->getTopSelling($limit);
        if (empty($products)) {
            $products = $this->productRepository->findWithFilters(
                search: null,
                categoryId: null,
                minPrice: null,
                maxPrice: null,
                inStock: null,
                sortBy: 'createdAt',
                sortOrder: 'DESC',
                page: 1,
                limit: $limit,
            );
        }

        return $products;
    }
}
