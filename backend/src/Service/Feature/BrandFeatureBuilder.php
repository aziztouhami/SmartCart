<?php

namespace App\Service\Feature;

use App\Repository\BrandRepository;

/**
 * Builds the flat per-brand feature vector for export to an offline
 * recommendation model.
 */
class BrandFeatureBuilder
{
    public function __construct(
        private BrandRepository $brandRepository,
        private InteractionAggregationService $aggregation,
    ) {
    }

    public function build(): array
    {
        $brands = $this->brandRepository->findAll();
        $productCounts = $this->aggregation->productCountsByBrand();
        $views = $this->aggregation->countsByType('view', 'p.brand', joinProduct: true);
        $carts = $this->aggregation->countsByType('cart', 'p.brand', joinProduct: true);
        $purchases = $this->aggregation->countsByType('purchase', 'p.brand', joinProduct: true);
        $distinctUsers = $this->aggregation->distinctUsersGroupedBy('p.brand');
        $favorites = $this->aggregation->favoritesGroupedBy('p.brand', joinProduct: true);
        $reviews = $this->aggregation->reviewsGroupedBy('p.brand', joinProduct: true);

        $rows = [];
        foreach ($brands as $brand) {
            $id = $brand->getId();
            $viewCount = $views[$id]['count'] ?? 0;
            $purchaseCount = $purchases[$id]['count'] ?? 0;

            $rows[] = [
                'brandId' => $id,
                'name' => $brand->getName(),
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
