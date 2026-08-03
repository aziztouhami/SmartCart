<?php

namespace App\Service\Feature;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Low-level grouped-aggregate queries (interaction counts, favorites, reviews,
 * order stats, catalog counts) shared by the per-entity feature builders.
 * All aggregates are computed with grouped DQL queries to avoid N+1 lookups
 * across the whole catalog/user base.
 */
class InteractionAggregationService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Counts (and summed value, e.g. quantity) of interactions of $type,
     * grouped by $groupBy ('i.product', 'i.user', 'p.category' or 'p.brand').
     *
     * @return array<int, array{count: int, qty: int}>
     */
    public function countsByType(string $type, string $groupBy, bool $joinProduct = false): array
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
            if (null === $row['id']) {
                continue;
            }
            $map[(int) $row['id']] = ['count' => (int) $row['cnt'], 'qty' => (int) $row['qty']];
        }

        return $map;
    }

    /**
     * @return array<int, int>
     */
    public function distinctUsersGroupedBy(string $groupBy): array
    {
        $rows = $this->em->createQuery(
            "SELECT IDENTITY({$groupBy}) AS id, COUNT(DISTINCT i.user) AS cnt
             FROM App\Entity\Interaction i
             JOIN i.product p
             GROUP BY {$groupBy}"
        )->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            if (null === $row['id']) {
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
    public function distinctEngagedGroupedBy(string $distinctField, string $groupBy): array
    {
        $rows = $this->em->createQuery(
            "SELECT IDENTITY({$groupBy}) AS id, COUNT(DISTINCT {$distinctField}) AS cnt
             FROM App\Entity\Interaction i
             JOIN i.product p
             GROUP BY {$groupBy}"
        )->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            if (null === $row['id']) {
                continue;
            }
            $map[(int) $row['id']] = (int) $row['cnt'];
        }

        return $map;
    }

    /**
     * @return array<int, \DateTimeImmutable>
     */
    public function lastInteractionGroupedBy(string $groupBy): array
    {
        $rows = $this->em->createQuery(
            "SELECT IDENTITY({$groupBy}) AS id, MAX(i.createdAt) AS last
             FROM App\Entity\Interaction i
             GROUP BY {$groupBy}"
        )->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            if (null === $row['id']) {
                continue;
            }
            $map[(int) $row['id']] = new \DateTimeImmutable($row['last']);
        }

        return $map;
    }

    /**
     * @return array<int, int>
     */
    public function favoritesGroupedBy(string $groupBy, bool $joinProduct = false): array
    {
        $join = $joinProduct ? ' JOIN f.product p' : '';
        $rows = $this->em->createQuery(
            "SELECT IDENTITY({$groupBy}) AS id, COUNT(f.id) AS cnt
             FROM App\Entity\Favorite f{$join}
             GROUP BY {$groupBy}"
        )->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            if (null === $row['id']) {
                continue;
            }
            $map[(int) $row['id']] = (int) $row['cnt'];
        }

        return $map;
    }

    /**
     * @return array<int, array{count: int, avg: float}>
     */
    public function reviewsGroupedBy(string $groupBy, bool $joinProduct = false): array
    {
        $join = $joinProduct ? ' JOIN r.product p' : '';
        $rows = $this->em->createQuery(
            "SELECT IDENTITY({$groupBy}) AS id, COUNT(r.id) AS cnt, AVG(r.rating) AS avgRating
             FROM App\Entity\Review r{$join}
             GROUP BY {$groupBy}"
        )->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            if (null === $row['id']) {
                continue;
            }
            $map[(int) $row['id']] = ['count' => (int) $row['cnt'], 'avg' => (float) $row['avgRating']];
        }

        return $map;
    }

    /**
     * @return array<int, array{count: int, total: float}>
     */
    public function orderStatsByUser(): array
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
    public function productCountsByCategory(): array
    {
        $rows = $this->em->createQuery(
            'SELECT IDENTITY(p.category) AS id, COUNT(p.id) AS cnt
             FROM App\Entity\Product p
             GROUP BY p.category'
        )->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            if (null === $row['id']) {
                continue;
            }
            $map[(int) $row['id']] = (int) $row['cnt'];
        }

        return $map;
    }

    /**
     * @return array<int, int>
     */
    public function productCountsByBrand(): array
    {
        $rows = $this->em->createQuery(
            'SELECT IDENTITY(p.brand) AS id, COUNT(p.id) AS cnt
             FROM App\Entity\Product p
             GROUP BY p.brand'
        )->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            if (null === $row['id']) {
                continue;
            }
            $map[(int) $row['id']] = (int) $row['cnt'];
        }

        return $map;
    }

    /**
     * @return array<int, int>
     */
    public function productCountsByProductType(): array
    {
        $rows = $this->em->createQuery(
            'SELECT IDENTITY(p.productType) AS id, COUNT(p.id) AS cnt
             FROM App\Entity\Product p
             GROUP BY p.productType'
        )->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            if (null === $row['id']) {
                continue;
            }
            $map[(int) $row['id']] = (int) $row['cnt'];
        }

        return $map;
    }

    /**
     * Units sold per week for the last $weeks weeks, scoped to a single
     * entity — used by the AI analytics feature to show the model a
     * sell-through trend without needing any dedicated price/stock history
     * table (none exists; this is derived entirely from existing purchase
     * interactions, which already carry a timestamp and quantity).
     *
     * $groupBy is a DQL path identifying the scope: 'i.product' analyzes one
     * product directly; 'p.category', 'p.brand', 'p.productType' analyze
     * every product under that category/brand/type via a join, matching the
     * joinProduct convention used elsewhere in this class.
     *
     * Bucketing is done in PHP (not SQL date-truncation) to stay portable
     * across DB drivers, consistent with the rest of this class.
     *
     * @return array<int, array{weekStart: string, unitsSold: int}> oldest week first
     */
    public function purchaseTimeSeries(string $groupBy, int $entityId, int $weeks = 8): array
    {
        $joinProduct = !str_starts_with($groupBy, 'i.');
        $join = $joinProduct ? ' JOIN i.product p' : '';
        $since = new \DateTimeImmutable("-{$weeks} weeks");

        $rows = $this->em->createQuery(
            "SELECT i.createdAt AS createdAt, i.value AS value
             FROM App\Entity\Interaction i{$join}
             WHERE i.type = 'purchase' AND {$groupBy} = :entityId AND i.createdAt >= :since"
        )
            ->setParameter('entityId', $entityId)
            ->setParameter('since', $since)
            ->getArrayResult();

        // Pre-fill every week in range (oldest first) so the model sees
        // explicit zeros for weeks with no sales, not gaps.
        $buckets = [];
        $cursor = $since->modify('monday this week');
        for ($i = 0; $i < $weeks; ++$i) {
            $buckets[$cursor->format('Y-m-d')] = 0;
            $cursor = $cursor->modify('+1 week');
        }

        foreach ($rows as $row) {
            $createdAt = $row['createdAt'] instanceof \DateTimeInterface
                ? $row['createdAt']
                : new \DateTimeImmutable((string) $row['createdAt']);
            $weekStart = $createdAt->modify('monday this week')->format('Y-m-d');
            $buckets[$weekStart] = ($buckets[$weekStart] ?? 0) + (int) $row['value'];
        }

        ksort($buckets);

        $series = [];
        foreach ($buckets as $weekStart => $unitsSold) {
            $series[] = ['weekStart' => $weekStart, 'unitsSold' => $unitsSold];
        }

        return $series;
    }
}
