<?php

namespace App\Tests\Unit\Service;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Service\DashboardService;
use PHPUnit\Framework\TestCase;

class DashboardServiceTest extends TestCase
{
    private OrderRepository $orderRepository;
    private ProductRepository $productRepository;
    private UserRepository $userRepository;
    private CategoryRepository $categoryRepository;
    private DashboardService $service;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->categoryRepository = $this->createMock(CategoryRepository::class);

        $this->orderRepository->method('findAllOrders')->willReturn([]);
        $this->orderRepository->method('getTotalRevenue')->willReturn(0.0);
        $this->orderRepository->method('getMonthlyRevenue')->willReturn(0.0);
        $this->orderRepository->method('getDailyRevenue')->willReturn(0.0);
        $this->orderRepository->method('getRevenueByMonth')->willReturn([]);
        $this->orderRepository->method('countAllOrders')->willReturn(0);
        $this->orderRepository->method('countOrdersThisMonth')->willReturn(0);
        $this->orderRepository->method('countAllOrdersByStatus')->willReturn([]);
        $this->userRepository->method('countTotal')->willReturn(0);
        $this->userRepository->method('countNewThisMonth')->willReturn(0);
        $this->productRepository->method('findLowStock')->willReturn([]);
        $this->productRepository->method('getTopSelling')->willReturn([]);
        $this->productRepository->method('countAll')->willReturn(0);
        $this->productRepository->method('countLowStock')->willReturn(0);
        $this->productRepository->method('countOutOfStock')->willReturn(0);
        $this->categoryRepository->method('countAll')->willReturn(0);
        // findRoots/countByCategoryIds deliberately NOT stubbed here — set
        // them per-test instead, since a blanket default here would silently
        // win over a test-specific ->method() override on the same mock.

        $this->service = new DashboardService(
            $this->orderRepository,
            $this->productRepository,
            $this->userRepository,
            $this->categoryRepository,
        );
    }

    private function makeCategory(int $id, string $name): Category
    {
        $category = new Category();
        $ref = new \ReflectionProperty(Category::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($category, $id);
        $category->setName($name);

        return $category;
    }

    public function testRoundsRevenueFigures(): void
    {
        $this->categoryRepository->method('findRoots')->willReturn([]);
        $this->productRepository->method('countByCategoryIds')->willReturn([]);

        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->orderRepository->method('findAllOrders')->willReturn([]);
        $this->orderRepository->method('getTotalRevenue')->willReturn(1234.5678);
        $this->orderRepository->method('getMonthlyRevenue')->willReturn(99.999);
        $this->orderRepository->method('getDailyRevenue')->willReturn(0.0);
        $this->orderRepository->method('getRevenueByMonth')->willReturn([]);
        $this->orderRepository->method('countAllOrders')->willReturn(0);
        $this->orderRepository->method('countOrdersThisMonth')->willReturn(0);
        $this->orderRepository->method('countAllOrdersByStatus')->willReturn([]);

        $service = new DashboardService($this->orderRepository, $this->productRepository, $this->userRepository, $this->categoryRepository);
        $kpis = $service->getKpis(5);

        $this->assertSame(1234.57, $kpis['revenue']['total']);
        $this->assertSame(100.0, $kpis['revenue']['thisMonth']);
    }

    public function testTopCategoriesAreSortedByProductCountDescending(): void
    {
        $catA = $this->makeCategory(1, 'Electronics');
        $catB = $this->makeCategory(2, 'Books');
        $catC = $this->makeCategory(3, 'Toys');
        $this->categoryRepository->method('findRoots')->willReturn([$catA, $catB, $catC]);
        $this->productRepository->method('countByCategoryIds')->willReturn([1 => 5, 2 => 20, 3 => 10]);

        $kpis = $this->service->getKpis(5);

        $names = array_map(fn ($c) => $c['name'], $kpis['categories']['top']);
        $this->assertSame(['Books', 'Toys', 'Electronics'], $names);
    }

    public function testTopCategoriesCappedAtSix(): void
    {
        $categories = [];
        $counts = [];
        for ($i = 1; $i <= 8; ++$i) {
            $categories[] = $this->makeCategory($i, "Category {$i}");
            $counts[$i] = $i;
        }
        $this->categoryRepository->method('findRoots')->willReturn($categories);
        $this->productRepository->method('countByCategoryIds')->willReturn($counts);

        $kpis = $this->service->getKpis(5);

        $this->assertCount(6, $kpis['categories']['top']);
    }

    public function testMissingCategoryCountDefaultsToZero(): void
    {
        $category = $this->makeCategory(1, 'Electronics');
        $this->categoryRepository->method('findRoots')->willReturn([$category]);
        $this->productRepository->method('countByCategoryIds')->willReturn([]); // no entry for id 1

        $kpis = $this->service->getKpis(5);

        $this->assertSame(0, $kpis['categories']['top'][0]['productCount']);
    }

    public function testPassesLowStockThresholdThrough(): void
    {
        $this->categoryRepository->method('findRoots')->willReturn([]);
        $this->productRepository->method('countByCategoryIds')->willReturn([]);
        $this->productRepository->expects($this->once())->method('findLowStock')->with(3, 10)->willReturn([]);
        $this->productRepository->expects($this->once())->method('countLowStock')->with(3)->willReturn(0);

        $this->service->getKpis(3);
    }
}
