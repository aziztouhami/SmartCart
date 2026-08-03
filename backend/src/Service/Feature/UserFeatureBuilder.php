<?php

namespace App\Service\Feature;

use App\Repository\UserRepository;

/**
 * Builds the flat per-user feature vector (engagement, order history,
 * recency) for export to an offline recommendation model.
 */
class UserFeatureBuilder
{
    public function __construct(
        private UserRepository $userRepository,
        private InteractionAggregationService $aggregation,
    ) {
    }

    public function build(): array
    {
        $users = $this->userRepository->findAll();
        $views = $this->aggregation->countsByType('view', 'i.user');
        $carts = $this->aggregation->countsByType('cart', 'i.user');
        $purchases = $this->aggregation->countsByType('purchase', 'i.user');
        $orderStats = $this->aggregation->orderStatsByUser();
        $favorites = $this->aggregation->favoritesGroupedBy('f.user');
        $reviews = $this->aggregation->reviewsGroupedBy('r.user');
        $lastSeen = $this->aggregation->lastInteractionGroupedBy('i.user');
        $distinctCategories = $this->aggregation->distinctEngagedGroupedBy('p.category', 'i.user');
        $distinctBrands = $this->aggregation->distinctEngagedGroupedBy('p.brand', 'i.user');

        $now = new \DateTimeImmutable();
        $rows = [];
        foreach ($users as $user) {
            $id = $user->getId();
            $orderCount = $orderStats[$id]['count'] ?? 0;
            $totalSpent = $orderStats[$id]['total'] ?? 0.0;
            $lastActive = $lastSeen[$id] ?? null;

            $rows[] = [
                'userId' => $id,
                'email' => $user->getEmail(),
                'isVerified' => $user->isVerified(),
                'marketingOptIn' => $user->getMarketingOptIn(),
                'accountAgeDays' => $now->diff($user->getCreatedAt())->days,
                'views' => $views[$id]['count'] ?? 0,
                'cartAdds' => $carts[$id]['count'] ?? 0,
                'purchases' => $purchases[$id]['count'] ?? 0,
                'purchaseQuantity' => $purchases[$id]['qty'] ?? 0,
                'orderCount' => $orderCount,
                'totalSpent' => round($totalSpent, 2),
                'avgOrderValue' => $orderCount > 0 ? round($totalSpent / $orderCount, 2) : 0.0,
                'favoriteCount' => $favorites[$id] ?? 0,
                'reviewCount' => $reviews[$id]['count'] ?? 0,
                'avgRatingGiven' => isset($reviews[$id]) ? round($reviews[$id]['avg'], 2) : null,
                'distinctCategoriesEngaged' => $distinctCategories[$id] ?? 0,
                'distinctBrandsEngaged' => $distinctBrands[$id] ?? 0,
                'daysSinceLastActivity' => $lastActive ? $now->diff($lastActive)->days : null,
            ];
        }

        return $rows;
    }
}
