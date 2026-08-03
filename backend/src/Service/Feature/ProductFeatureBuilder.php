<?php

namespace App\Service\Feature;

use App\Repository\ProductRepository;

/**
 * Builds the flat per-product feature vector (views, cart adds, purchases,
 * recency, conversion rates, etc.) for export to an offline recommendation
 * model.
 */
class ProductFeatureBuilder
{
    public function __construct(
        private ProductRepository $productRepository,
        private InteractionAggregationService $aggregation,
    ) {
    }

    public function build(): array
    {
        $products = $this->productRepository->findAll();
        $views = $this->aggregation->countsByType('view', 'i.product');
        $carts = $this->aggregation->countsByType('cart', 'i.product');
        $purchases = $this->aggregation->countsByType('purchase', 'i.product');
        $favorites = $this->aggregation->favoritesGroupedBy('f.product');
        $reviews = $this->aggregation->reviewsGroupedBy('r.product');
        $lastSeen = $this->aggregation->lastInteractionGroupedBy('i.product');

        $now = new \DateTimeImmutable();
        $rows = [];
        foreach ($products as $product) {
            $id = $product->getId();
            $viewCount = $views[$id]['count'] ?? 0;
            $cartCount = $carts[$id]['count'] ?? 0;
            $purchaseCount = $purchases[$id]['count'] ?? 0;

            $rows[] = [
                'productId' => $id,
                'name' => $product->getName(),
                'categoryId' => $product->getCategory()?->getId(),
                'brandId' => $product->getBrand()?->getId(),
                'price' => (float) $product->getPrice(),
                'stock' => $product->getStock(),
                'ageDays' => $now->diff($product->getCreatedAt())->days,
                'views' => $viewCount,
                'cartAdds' => $cartCount,
                'cartQuantity' => $carts[$id]['qty'] ?? 0,
                'purchases' => $purchaseCount,
                'purchaseQuantity' => $purchases[$id]['qty'] ?? 0,
                'favorites' => $favorites[$id] ?? 0,
                'reviewCount' => $reviews[$id]['count'] ?? 0,
                'avgRating' => isset($reviews[$id]) ? round($reviews[$id]['avg'], 2) : null,
                'viewToCartRate' => $viewCount > 0 ? round($cartCount / $viewCount, 4) : 0.0,
                'cartToPurchaseRate' => $cartCount > 0 ? round($purchaseCount / $cartCount, 4) : 0.0,
                'conversionRate' => $viewCount > 0 ? round($purchaseCount / $viewCount, 4) : 0.0,
                'lastInteractionAt' => ($lastSeen[$id] ?? null)?->format('c'),
            ];
        }

        return $rows;
    }
}
