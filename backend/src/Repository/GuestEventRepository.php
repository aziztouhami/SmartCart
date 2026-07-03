<?php

namespace App\Repository;

use App\Entity\GuestEvent;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GuestEvent>
 */
class GuestEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GuestEvent::class);
    }

    public function save(GuestEvent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Distinct product ids touched by this session, most recent first —
     * the "what is this guest currently looking at" signal used to serve
     * live recommendations.
     *
     * @return int[]
     */
    public function findRecentProductIdsBySession(string $sessionId, int $limit = 10): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.product) AS productId, MAX(e.createdAt) AS lastSeen')
            ->andWhere('e.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->groupBy('e.product')
            ->orderBy('lastSeen', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(fn ($row) => (int) $row['productId'], $rows);
    }

    /**
     * All (sessionId => [productId => strongest event type]) groups within
     * the lookback window — the raw material for offline co-occurrence
     * mining. Sessions are the guest equivalent of a user's interaction
     * history in the authenticated co-occurrence pass.
     *
     * @return array<string, array<int, string>>
     */
    public function groupedSessionsSince(\DateTimeImmutable $since): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.sessionId AS sessionId, IDENTITY(e.product) AS productId, e.type AS type')
            ->andWhere('e.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getArrayResult();

        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['sessionId']][(int) $row['productId']] = $row['type'];
        }

        return $groups;
    }

    /**
     * The product each session touched *first* — what brand-new guests
     * tend to land on/search for, before anything else about them is
     * known. Feeds the cold-start "what new visitors usually look at"
     * signal (one entry per session, repeats matter — that's the
     * popularity signal).
     *
     * @return int[]
     */
    public function findFirstProductIdPerSession(): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.sessionId AS sessionId, IDENTITY(e.product) AS productId, e.createdAt AS createdAt')
            ->orderBy('e.sessionId', 'ASC')
            ->addOrderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $firstPerSession = [];
        foreach ($rows as $row) {
            $firstPerSession[$row['sessionId']] ??= (int) $row['productId'];
        }

        return array_values($firstPerSession);
    }

    /**
     * Event counts per product since $since — the guest half of the
     * "trending right now" signal.
     *
     * @return array<int, int>
     */
    public function countRecentByProduct(\DateTimeImmutable $since): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.product) AS productId, COUNT(e.id) AS cnt')
            ->andWhere('e.createdAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('e.product')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['productId']] = (int) $row['cnt'];
        }
        return $counts;
    }

    /**
     * Distinct guest sessions that viewed this product since $since — the
     * anonymous-visitor half of the product page's "X people viewing this
     * now" social-proof signal.
     */
    public function countDistinctSessionsViewingSince(Product $product, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(DISTINCT e.sessionId)')
            ->andWhere('e.product = :product')
            ->andWhere('e.type = :type')
            ->andWhere('e.createdAt >= :since')
            ->setParameter('product', $product)
            ->setParameter('type', 'view')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Drop events past the retention window — guest sessions are anonymous
     * but unbounded growth still isn't desirable, and stale sessions add no
     * value to the relatedness model.
     */
    public function pruneOlderThan(\DateTimeImmutable $cutoff): int
    {
        return (int) $this->createQueryBuilder('e')
            ->delete()
            ->andWhere('e.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}
