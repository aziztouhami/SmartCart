<?php

namespace App\Domain\Product;

use App\Repository\ProductRepository;
use App\Repository\PromotionRepository;

/**
 * Picks the products currently under an active promotion (any scope),
 * newest-first, capped to a limit — the rule behind `GET /api/products/promotions`.
 */
class PromotedProductsSelector
{
    public function __construct(
        private ProductRepository $productRepository,
        private PromotionRepository $promotionRepository,
    ) {
    }

    /**
     * @return array{products: array<int, object>, promoMap: array<int, object>}
     */
    public function select(int $limit): array
    {
        // No active promotion at all (the common case) — skip the
        // full-catalog load entirely instead of fetching every product just
        // to filter all of them out.
        if (empty($this->promotionRepository->findActive())) {
            return ['products' => [], 'promoMap' => []];
        }

        $allProducts = $this->productRepository->findAllWithRelations();
        $promoMap = $this->promotionRepository->findActiveForProducts($allProducts);

        $promoted = array_values(array_filter($allProducts, fn ($p) => isset($promoMap[$p->getId()])));
        usort($promoted, fn ($a, $b) => $b->getId() <=> $a->getId());
        $promoted = array_slice($promoted, 0, $limit);

        return ['products' => $promoted, 'promoMap' => $promoMap];
    }
}
