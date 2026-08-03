<?php

namespace App\Service\Feature;

use App\Repository\ProductTypeRepository;

/**
 * Builds the flat per-product-type feature vector — mirrors
 * CategoryFeatureBuilder/BrandFeatureBuilder exactly, grouped by
 * p.productType instead of p.category/p.brand.
 */
class ProductTypeFeatureBuilder
{
    public function __construct(
        private ProductTypeRepository $productTypeRepository,
        private InteractionAggregationService $aggregation,
    ) {
    }

    public function build(): array
    {
        $types = $this->productTypeRepository->findAll();
        $productCounts = $this->aggregation->productCountsByProductType();
        $views = $this->aggregation->countsByType('view', 'p.productType', joinProduct: true);
        $carts = $this->aggregation->countsByType('cart', 'p.productType', joinProduct: true);
        $purchases = $this->aggregation->countsByType('purchase', 'p.productType', joinProduct: true);
        $distinctUsers = $this->aggregation->distinctUsersGroupedBy('p.productType');
        $favorites = $this->aggregation->favoritesGroupedBy('p.productType', joinProduct: true);
        $reviews = $this->aggregation->reviewsGroupedBy('p.productType', joinProduct: true);

        $rows = [];
        foreach ($types as $type) {
            $id = $type->getId();
            $viewCount = $views[$id]['count'] ?? 0;
            $purchaseCount = $purchases[$id]['count'] ?? 0;

            $rows[] = [
                'productTypeId' => $id,
                'name' => $type->getName(),
                'productCount' => $productCounts[$id] ?? 0,
                'views' => $viewCount,
                'cartAdds' => $carts[$id]['count'] ?? 0,
                'purchases' => $purchaseCount,
                'purchaseQuantity' => $purchases[$id]['qty'] ?? 0,
                'distinctUsersEngaged' => $distinctUsers[$id] ?? 0,
                'favorites' => $favorites[$id] ?? 0,
                'reviewCount' => $reviews[$id]['count'] ?? 0,
                'avgRating' => isset($reviews[$id]) ? round($reviews[$id]['avg'], 2) : null,
                'conversionRate' => $viewCount > 0 ? round($purchaseCount / $viewCount, 4) : 0.0,
            ];
        }

        return $rows;
    }
}
