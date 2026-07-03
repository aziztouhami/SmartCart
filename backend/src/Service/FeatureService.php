<?php

namespace App\Service;

use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Builds flat feature vectors (views, cart adds, purchases, recency,
 * monetary value, etc.) per product/category/brand/user for export to an
 * offline recommendation model. All aggregates are computed with grouped
 * DQL queries to avoid N+1 lookups across the whole catalog/user base.
 */
class FeatureService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private BrandRepository $brandRepository,
        private UserRepository $userRepository,
    ) {}

    public function getProductFeatures(): array
    {
        $products  = $this->productRepository->findAll();
        $views     = $this->countsByType('view', 'i.product');
        $carts     = $this->countsByType('cart', 'i.product');
        $purchases = $this->countsByType('purchase', 'i.product');
        $favorites = $this->favoritesGroupedBy('f.product');
        $reviews   = $this->reviewsGroupedBy('r.product');
        $lastSeen  = $this->lastInteractionGroupedBy('i.product');

        $now  = new \DateTimeImmutable();
        $rows = [];
        foreach ($products as $product) {
            $id            = $product->getId();
            $viewCount     = $views[$id]['count'] ?? 0;
            $cartCount     = $carts[$id]['count'] ?? 0;
            $purchaseCount = $purchases[$id]['count'] ?? 0;

            $rows[] = [
                'productId'          => $id,
                'name'               => $product->getName(),
                'categoryId'         => $product->getCategory()?->getId(),
                'brandId'            => $product->getBrand()?->getId(),
                'price'              => (float) $product->getPrice(),
                'stock'              => $product->getStock(),
                'ageDays'            => $now->diff($product->getCreatedAt())->days,
                'views'              => $viewCount,
                'cartAdds'           => $cartCount,
                'cartQuantity'       => $carts[$id]['qty'] ?? 0,
                'purchases'          => $purchaseCount,
                'purchaseQuantity'   => $purchases[$id]['qty'] ?? 0,
                'favorites'          => $favorites[$id] ?? 0,
                'reviewCount'        => $reviews[$id]['count'] ?? 0,
                'avgRating'          => isset($reviews[$id]) ? round($reviews[$id]['avg'], 2) : null,
                'viewToCartRate'     => $viewCount > 0 ? round($cartCount / $viewCount, 4) : 0.0,
                'cartToPurchaseRate' => $cartCount > 0 ? round($purchaseCount / $cartCount, 4) : 0.0,
                'conversionRate'     => $viewCount > 0 ? round($purchaseCount / $viewCount, 4) : 0.0,
                'lastInteractionAt'  => ($lastSeen[$id] ?? null)?->format('c'),
            ];
        }

        return $rows;
    }

    public function getCategoryFeatures(): array
    {
        $categories     = $this->categoryRepository->findAll();
        $productCounts  = $this->productCountsByCategory();
        $views          = $this->countsByType('view', 'p.category', joinProduct: true);
        $carts          = $this->countsByType('cart', 'p.category', joinProduct: true);
        $purchases      = $this->countsByType('purchase', 'p.category', joinProduct: true);
        $distinctUsers  = $this->distinctUsersGroupedBy('p.category');
        $favorites      = $this->favoritesGroupedBy('p.category', joinProduct: true);
        $reviews        = $this->reviewsGroupedBy('p.category', joinProduct: true);

        $rows = [];
        foreach ($categories as $category) {
            $id            = $category->getId();
            $viewCount     = $views[$id]['count'] ?? 0;
            $purchaseCount = $purchases[$id]['count'] ?? 0;

            $rows[] = [
                'categoryId'           => $id,
                'name'                 => $category->getName(),
                'productCount'         => $productCounts[$id] ?? 0,
                'views'                => $viewCount,
                'cartAdds'             => $carts[$id]['count'] ?? 0,
                'purchases'            => $purchaseCount,
                'purchaseQuantity'     => $purchases[$id]['qty'] ?? 0,
                'distinctUsersEngaged' => $distinctUsers[$id] ?? 0,
                'favorites'            => $favorites[$id] ?? 0,
                'reviewCount'          => $reviews[$id]['count'] ?? 0,
                'avgRating'            => isset($reviews[$id]) ? round($reviews[$id]['avg'], 2) : null,
                'conversionRate'       => $viewCount > 0 ? round($purchaseCount / $viewCount, 4) : 0.0,
            ];
        }

        return $rows;
    }

    public function getBrandFeatures(): array
    {
        $brands        = $this->brandRepository->findAll();
        $productCounts = $this->productCountsByBrand();
        $views         = $this->countsByType('view', 'p.brand', joinProduct: true);
        $carts         = $this->countsByType('cart', 'p.brand', joinProduct: true);
        $purchases     = $this->countsByType('purchase', 'p.brand', joinProduct: true);
        $distinctUsers = $this->distinctUsersGroupedBy('p.brand');
        $favorites     = $this->favoritesGroupedBy('p.brand', joinProduct: true);
        $reviews       = $this->reviewsGroupedBy('p.brand', joinProduct: true);

        $rows = [];
        foreach ($brands as $brand) {
            $id            = $brand->getId();
            $viewCount     = $views[$id]['count'] ?? 0;
            $purchaseCount = $purchases[$id]['count'] ?? 0;

            $rows[] = [
                'brandId'              => $id,
                'name'                 => $brand->getName(),
                'productCount'         => $productCounts[$id] ?? 0,
                'views'                => $viewCount,
                'cartAdds'             => $carts[$id]['count'] ?? 0,
                'purchases'            => $purchaseCount,
                'purchaseQuantity'     => $purchases[$id]['qty'] ?? 0,
                'distinctUsersEngaged' => $distinctUsers[$id] ?? 0,
                'favorites'            => $favorites[$id] ?? 0,
                'reviewCount'          => $reviews[$id]['count'] ?? 0,
                'avgRating'            => isset($reviews[$id]) ? round($reviews[$id]['avg'], 2) : null,
                'conversionRate'       => $viewCount > 0 ? round($purchaseCount / $viewCount, 4) : 0.0,
            ];
        }

        return $rows;
    }

    public function getUserFeatures(): array
    {
        $users              = $this->userRepository->findAll();
        $views              = $this->countsByType('view', 'i.user');
        $carts              = $this->countsByType('cart', 'i.user');
        $purchases          = $this->countsByType('purchase', 'i.user');
        $orderStats         = $this->orderStatsByUser();
        $favorites          = $this->favoritesGroupedBy('f.user');
        $reviews            = $this->reviewsGroupedBy('r.user');
        $lastSeen           = $this->lastInteractionGroupedBy('i.user');
        $distinctCategories = $this->distinctEngagedGroupedBy('p.category', 'i.user');
        $distinctBrands     = $this->distinctEngagedGroupedBy('p.brand', 'i.user');

        $now  = new \DateTimeImmutable();
        $rows = [];
        foreach ($users as $user) {
            $id         = $user->getId();
            $orderCount = $orderStats[$id]['count'] ?? 0;
            $totalSpent = $orderStats[$id]['total'] ?? 0.0;
            $lastActive = $lastSeen[$id] ?? null;

            $rows[] = [
                'userId'                    => $id,
                'email'                     => $user->getEmail(),
                'isVerified'                => $user->isVerified(),
                'marketingOptIn'            => $user->getMarketingOptIn(),
                'accountAgeDays'            => $now->diff($user->getCreatedAt())->days,
                'views'                     => $views[$id]['count'] ?? 0,
                'cartAdds'                  => $carts[$id]['count'] ?? 0,
                'purchases'                 => $purchases[$id]['count'] ?? 0,
                'purchaseQuantity'          => $purchases[$id]['qty'] ?? 0,
                'orderCount'                => $orderCount,
                'totalSpent'                => round($totalSpent, 2),
                'avgOrderValue'             => $orderCount > 0 ? round($totalSpent / $orderCount, 2) : 0.0,
                'favoriteCount'             => $favorites[$id] ?? 0,
                'reviewCount'               => $reviews[$id]['count'] ?? 0,
                'avgRatingGiven'            => isset($reviews[$id]) ? round($reviews[$id]['avg'], 2) : null,
                'distinctCategoriesEngaged' => $distinctCategories[$id] ?? 0,
                'distinctBrandsEngaged'     => $distinctBrands[$id] ?? 0,
                'daysSinceLastActivity'     => $lastActive ? $now->diff($lastActive)->days : null,
            ];
        }

        return $rows;
    }

    /**
     * Counts (and summed value, e.g. quantity) of interactions of $type,
     * grouped by $groupBy ('i.product', 'i.user', 'p.category' or 'p.brand').
     *
     * @return array<int, array{count: int, qty: int}>
     */
    private function countsByType(string $type, string $groupBy, bool $joinProduct = false): array
    {
        $join = $joinProduct ? ' JOIN i.product p' : '';
        $rows = $this->em->createQuery(
            "SELECT IDENTITY({$groupBy}) AS id, COUNT(i.id) AS cnt, COALESCE(SUM(i.value), 0) AS qty
             FROM App\Entity\Interaction i{$join}
             WHERE i.type = :type
             GROUP BY {$groupBy}"
        )->setParameter('type', $type)->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            if ($row['id'] === null) {
                continue;
            }
            $map[(int) $row['id']] = ['count' => (int) $row['cnt'], 'qty' => (int) $row['qty']];
        }

        return $map;
    }

    /**
     * @return array<int, int>
     */
    private function distinctUsersGroupedBy(string $groupBy): array
    {
        $rows = $this->em->createQuery(
            "SELECT IDENTITY({$groupBy}) AS id, COUNT(DISTINCT i.user) AS cnt
             FROM App\Entity\Interaction i
             JOIN i.product p
             GROUP BY {$groupBy}"
        )->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            if ($row['id'] === null) {
                continue;
            }
            $map[(int) $row['id']] = (int) $row['cnt'];
        }

        return $map;
    }

    /**
     * Distinct count of $distinctField (e.g. 'p.category') engaged with,
     * grouped by $groupBy (e.g. 'i.user').
     *
     * @return array<int, int>
     */
    private function distinctEngagedGroupedBy(string $distinctField, string $groupBy): array
    {
        $rows = $this->em->createQuery(
            "SELECT IDENTITY({$groupBy}) AS id, COUNT(DISTINCT {$distinctField}) AS cnt
             FROM App\Entity\Interaction i
             JOIN i.product p
             GROUP BY {$groupBy}"
        )->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            if ($row['id'] === null) {
                continue;
            }
            $map[(int) $row['id']] = (int) $row['cnt'];
        }

        return $map;
    }

    /**
     * @return array<int, \DateTimeImmutable>
     */
    private function lastInteractionGroupedBy(string $groupBy): array
    {
        $rows = $this->em->createQuery(
            "SELECT IDENTITY({$groupBy}) AS id, MAX(i.createdAt) AS last
             FROM App\Entity\Interaction i
             GROUP BY {$groupBy}"
        )->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            if ($row['id'] === null) {
                continue;
            }
            $map[(int) $row['id']] = new \DateTimeImmutable($row['last']);
        }

        return $map;
    }

    /**
     * @return array<int, int>
     */
    private function favoritesGroupedBy(string $groupBy, bool $joinProduct = false): array
    {
        $join = $joinProduct ? ' JOIN f.product p' : '';
        $rows = $this->em->createQuery(
            "SELECT IDENTITY({$groupBy}) AS id, COUNT(f.id) AS cnt
             FROM App\Entity\Favorite f{$join}
             GROUP BY {$groupBy}"
        )->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            if ($row['id'] === null) {
                continue;
            }
            $map[(int) $row['id']] = (int) $row['cnt'];
        }

        return $map;
    }

    /**
     * @return array<int, array{count: int, avg: float}>
     */
    private function reviewsGroupedBy(string $groupBy, bool $joinProduct = false): array
    {
        $join = $joinProduct ? ' JOIN r.product p' : '';
        $rows = $this->em->createQuery(
            "SELECT IDENTITY({$groupBy}) AS id, COUNT(r.id) AS cnt, AVG(r.rating) AS avgRating
             FROM App\Entity\Review r{$join}
             GROUP BY {$groupBy}"
        )->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            if ($row['id'] === null) {
                continue;
            }
            $map[(int) $row['id']] = ['count' => (int) $row['cnt'], 'avg' => (float) $row['avgRating']];
        }

        return $map;
    }

    /**
     * @return array<int, array{count: int, total: float}>
     */
    private function orderStatsByUser(): array
    {
        $rows = $this->em->createQuery(
            'SELECT IDENTITY(o.user) AS id, COUNT(o.id) AS cnt, COALESCE(SUM(o.totalAmount), 0) AS total
             FROM App\Entity\Order o
             WHERE o.status != :cart
             GROUP BY o.user'
        )->setParameter('cart', 'cart')->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['id']] = ['count' => (int) $row['cnt'], 'total' => (float) $row['total']];
        }

        return $map;
    }

    /**
     * @return array<int, int>
     */
    private function productCountsByCategory(): array
    {
        $rows = $this->em->createQuery(
            'SELECT IDENTITY(p.category) AS id, COUNT(p.id) AS cnt
             FROM App\Entity\Product p
             GROUP BY p.category'
        )->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            if ($row['id'] === null) {
                continue;
            }
            $map[(int) $row['id']] = (int) $row['cnt'];
        }

        return $map;
    }

    /**
     * @return array<int, int>
     */
    private function productCountsByBrand(): array
    {
        $rows = $this->em->createQuery(
            'SELECT IDENTITY(p.brand) AS id, COUNT(p.id) AS cnt
             FROM App\Entity\Product p
             GROUP BY p.brand'
        )->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            if ($row['id'] === null) {
                continue;
            }
            $map[(int) $row['id']] = (int) $row['cnt'];
        }

        return $map;
    }
}
