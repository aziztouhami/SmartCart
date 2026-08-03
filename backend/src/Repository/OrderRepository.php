<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function save(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find orders by user.
     */
    public function findByUser(User $user, int $limit = 10, int $offset = 0): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.user = :user')
            ->setParameter('user', $user)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find orders by status.
     */
    public function findByStatus(string $status, int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.status = :status')
            ->setParameter('status', $status)
            ->setMaxResults($limit)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent orders.
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->setMaxResults($limit)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count orders by user.
     */
    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find the current active cart for a user (there is at most one per user).
     */
    public function findCartByUser(User $user): ?Order
    {
        return $this->findOneBy(['user' => $user, 'status' => 'cart']);
    }

    /**
     * Find all non-cart orders for a user (their order history).
     */
    public function findUserOrders(User $user, int $page = 1, int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.user = :user')
            ->andWhere('o.status != :cart')
            ->setParameter('user', $user)
            ->setParameter('cart', 'cart')
            ->orderBy('o.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUserOrders(User $user): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.user = :user')
            ->andWhere('o.status != :cart')
            ->setParameter('user', $user)
            ->setParameter('cart', 'cart')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Admin: all orders (no carts), with optional status filter.
     */
    public function findAllOrders(?string $status = null, int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.status != :cart')
            ->setParameter('cart', 'cart')
            ->orderBy('o.createdAt', 'DESC');

        if (null !== $status) {
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
        }

        return $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAllOrders(?string $status = null): int
    {
        $qb = $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.status != :cart')
            ->setParameter('cart', 'cart');

        if (null !== $status) {
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Count a specific user's orders grouped by status (for the dashboard).
     * Returns an array like ['pending' => 2, 'confirmed' => 1, ...].
     */
    public function countUserOrdersByStatus(User $user): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('o.status, COUNT(o.id) AS cnt')
            ->andWhere('o.user = :user')
            ->andWhere('o.status != :cart')
            ->setParameter('user', $user)
            ->setParameter('cart', 'cart')
            ->groupBy('o.status')
            ->getQuery()
            ->getResult();

        $counts = array_fill_keys(['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'], 0);
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['cnt'];
        }

        return $counts;
    }

    public function getTotalRevenue(): float
    {
        return (float) ($this->createQueryBuilder('o')
            ->select('SUM(o.totalAmount)')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('statuses', ['confirmed', 'shipped', 'delivered'])
            ->getQuery()
            ->getSingleScalarResult() ?? 0);
    }

    public function getMonthlyRevenue(): float
    {
        $start = new \DateTimeImmutable('first day of this month 00:00:00');
        $end = new \DateTimeImmutable('last day of this month 23:59:59');

        return (float) ($this->createQueryBuilder('o')
            ->select('SUM(o.totalAmount)')
            ->andWhere('o.status IN (:statuses)')
            ->andWhere('o.createdAt BETWEEN :start AND :end')
            ->setParameter('statuses', ['confirmed', 'shipped', 'delivered'])
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);
    }

    public function getDailyRevenue(): float
    {
        $start = new \DateTimeImmutable('today 00:00:00');
        $end = new \DateTimeImmutable('today 23:59:59');

        return (float) ($this->createQueryBuilder('o')
            ->select('SUM(o.totalAmount)')
            ->andWhere('o.status IN (:statuses)')
            ->andWhere('o.createdAt BETWEEN :start AND :end')
            ->setParameter('statuses', ['confirmed', 'shipped', 'delivered'])
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);
    }

    /**
     * Revenue grouped by calendar month for the last $months months (oldest first, current month last).
     *
     * @return array<int, array{month: string, year: int, value: float}>
     */
    public function getRevenueByMonth(int $months = 6): array
    {
        $result = [];

        for ($i = $months - 1; $i >= 0; --$i) {
            $start = new \DateTimeImmutable("first day of -{$i} month 00:00:00");
            $end = $start->modify('last day of this month 23:59:59');

            $value = (float) ($this->createQueryBuilder('o')
                ->select('SUM(o.totalAmount)')
                ->andWhere('o.status IN (:statuses)')
                ->andWhere('o.createdAt BETWEEN :start AND :end')
                ->setParameter('statuses', ['confirmed', 'shipped', 'delivered'])
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->getQuery()
                ->getSingleScalarResult() ?? 0);

            $result[] = [
                'month' => $start->format('M'),
                'year' => (int) $start->format('Y'),
                'value' => round($value, 2),
            ];
        }

        return $result;
    }

    public function countOrdersThisMonth(): int
    {
        $start = new \DateTimeImmutable('first day of this month 00:00:00');

        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.status != :cart')
            ->andWhere('o.createdAt >= :start')
            ->setParameter('cart', 'cart')
            ->setParameter('start', $start)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAllOrdersByStatus(): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('o.status, COUNT(o.id) AS cnt')
            ->andWhere('o.status != :cart')
            ->setParameter('cart', 'cart')
            ->groupBy('o.status')
            ->getQuery()
            ->getResult();

        $counts = array_fill_keys(['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'], 0);
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * Product ids this user has actually bought (any placed order, not just
     * delivered ones) — the recommendation engine's "don't suggest what
     * they already own" exclusion list.
     *
     * @return int[]
     */
    public function findPurchasedProductIds(User $user): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('IDENTITY(oi.product) AS productId')
            ->join('o.orderItems', 'oi')
            ->andWhere('o.user = :user')
            ->andWhere('o.status != :cart')
            ->setParameter('user', $user)
            ->setParameter('cart', 'cart')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_unique(array_map(fn ($row) => (int) $row['productId'], $rows)));
    }

    /**
     * How many distinct shoppers currently have this product sitting in
     * their active cart right now — the product page's "in X carts"
     * social-proof signal.
     */
    public function countActiveCartsContainingProduct(Product $product): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(DISTINCT o.id)')
            ->join('o.orderItems', 'oi')
            ->andWhere('o.status = :status')
            ->andWhere('oi.product = :product')
            ->setParameter('status', 'cart')
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasUserDeliveredOrderWithProduct(User $user, Product $product): bool
    {
        return null !== $this->createQueryBuilder('o')
            ->join('o.orderItems', 'oi')
            ->andWhere('o.user = :user')
            ->andWhere('oi.product = :product')
            ->andWhere('o.status = :status')
            ->setParameter('user', $user)
            ->setParameter('product', $product)
            ->setParameter('status', 'delivered')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Units sold per (category, calendar month) across all placed (non-cart)
     * orders, regardless of year — the raw input for a seasonality analysis.
     * Not used by any serving path yet; feeds the standalone
     * app:analyze-seasonal-trends command only.
     *
     * @return array<int, array{categoryId: int, month: int, quantity: int}>
     */
    public function sumQuantityByCategoryAndMonth(): array
    {
        // Raw SQL: DQL has no EXTRACT()/date-part function registered, and
        // this is an offline analysis query, not something serving needs.
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT p.category_id AS category_id,
                    EXTRACT(MONTH FROM o.created_at)::int AS month,
                    SUM(oi.quantity) AS quantity
             FROM "order" o
             JOIN order_item oi ON oi.order_id = o.id
             JOIN product p ON p.id = oi.product_id
             WHERE o.status != :cart
             GROUP BY p.category_id, month',
            ['cart' => 'cart']
        );

        return array_map(fn ($row) => [
            'categoryId' => (int) $row['category_id'],
            'month' => (int) $row['month'],
            'quantity' => (int) $row['quantity'],
        ], $rows);
    }

    /**
     * Returns a userId => orderCount map for the given user IDs (single query, no N+1).
     */
    public function countOrdersPerUser(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $rows = $this->createQueryBuilder('o')
            ->select('IDENTITY(o.user) AS userId, COUNT(o.id) AS cnt')
            ->andWhere('o.user IN (:userIds)')
            ->andWhere('o.status != :cart')
            ->setParameter('userIds', $userIds)
            ->setParameter('cart', 'cart')
            ->groupBy('o.user')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['userId']] = (int) $row['cnt'];
        }

        return $result;
    }
}
