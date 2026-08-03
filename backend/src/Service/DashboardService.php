<?php

namespace App\Service;

use App\DTO\Order\OrderListItem;
use App\DTO\Product\ProductListItem;
use App\Repository\CategoryRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;

class DashboardService
{
    public function __construct(
        private OrderRepository $orderRepository,
        private ProductRepository $productRepository,
        private UserRepository $userRepository,
        private CategoryRepository $categoryRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getKpis(int $lowStockThreshold): array
    {
        $lowStockProducts = $this->productRepository->findLowStock($lowStockThreshold, 10);
        $topSelling = $this->productRepository->getTopSelling(5);
        $recentOrders = $this->orderRepository->findAllOrders(null, 1, 10);

        $roots = $this->categoryRepository->findRoots();
        $productCounts = $this->productRepository->countByCategoryIds(array_map(fn ($c) => $c->getId(), $roots));
        $topCategories = array_map(
            fn ($c) => ['id' => $c->getId(), 'name' => $c->getName(), 'productCount' => $productCounts[$c->getId()] ?? 0],
            $roots
        );
        usort($topCategories, fn ($a, $b) => $b['productCount'] - $a['productCount']);

        return [
            'revenue' => [
                'total' => round($this->orderRepository->getTotalRevenue(), 2),
                'thisMonth' => round($this->orderRepository->getMonthlyRevenue(), 2),
                'today' => round($this->orderRepository->getDailyRevenue(), 2),
                'monthly' => $this->orderRepository->getRevenueByMonth(6),
            ],
            'orders' => [
                'total' => $this->orderRepository->countAllOrders(),
                'thisMonth' => $this->orderRepository->countOrdersThisMonth(),
                'byStatus' => $this->orderRepository->countAllOrdersByStatus(),
            ],
            'users' => [
                'total' => $this->userRepository->countTotal(),
                'newThisMonth' => $this->userRepository->countNewThisMonth(),
            ],
            'products' => [
                'total' => $this->productRepository->countAll(),
                'lowStockCount' => $this->productRepository->countLowStock($lowStockThreshold),
                'outOfStockCount' => $this->productRepository->countOutOfStock(),
                'lowStock' => array_map(
                    fn ($p) => ProductListItem::fromEntity($p),
                    $lowStockProducts
                ),
            ],
            'categories' => [
                'total' => $this->categoryRepository->countAll(),
                'top' => array_slice($topCategories, 0, 6),
            ],
            'topSelling' => array_map(fn ($p) => ProductListItem::fromEntity($p), $topSelling),
            'recentOrders' => array_map(fn ($o) => OrderListItem::fromEntity($o), $recentOrders),
        ];
    }
}
