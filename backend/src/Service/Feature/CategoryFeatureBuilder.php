<?php

namespace App\Service\Feature;

use App\Repository\CategoryRepository;

/**
 * Builds the flat per-category feature vector for export to an offline
 * recommendation model.
 */
class CategoryFeatureBuilder
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private InteractionAggregationService $aggregation,
    ) {
    }

    public function build(): array
    {
        $categories = $this->categoryRepository->findAll();
        $productCounts = $this->aggregation->productCountsByCategory();
        $views = $this->aggregation->countsByType('view', 'p.category', joinProduct: true);
        $carts = $this->aggregation->countsByType('cart', 'p.category', joinProduct: true);
        $purchases = $this->aggregation->countsByType('purchase', 'p.category', joinProduct: true);
        $distinctUsers = $this->aggregation->distinctUsersGroupedBy('p.category');
        $favorites = $this->aggregation->favoritesGroupedBy('p.category', joinProduct: true);
        $reviews = $this->aggregation->reviewsGroupedBy('p.category', joinProduct: true);

        $rows = [];
        foreach ($categories as $category) {
            $id = $category->getId();
            $viewCount = $views[$id]['count'] ?? 0;
            $purchaseCount = $purchases[$id]['count'] ?? 0;

            $rows[] = [
                'categoryId' => $id,
                'name' => $category->getName(),
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
