<?php

namespace App\Repository;

use App\Entity\Interaction;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Interaction>
 */
class InteractionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Interaction::class);
    }

    public function save(Interaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Interaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * The product each user touched *first* — the authenticated-user half
     * of the cold-start "what new visitors usually look at" signal.
     *
     * @return int[]
     */
    public function findFirstProductIdPerUser(): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('IDENTITY(i.user) AS userId, IDENTITY(i.product) AS productId, i.createdAt AS createdAt')
            ->orderBy('i.user', 'ASC')
            ->addOrderBy('i.createdAt', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $firstPerUser = [];
        foreach ($rows as $row) {
            $firstPerUser[$row['userId']] ??= (int) $row['productId'];
        }

        return array_values($firstPerUser);
    }

    /**
     * Interaction counts per product since $since — the authenticated half
     * of the "trending right now" signal.
     *
     * @return array<int, int>
     */
    public function countRecentByProduct(\DateTimeImmutable $since): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('IDENTITY(i.product) AS productId, COUNT(i.id) AS cnt')
            ->andWhere('i.createdAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('i.product')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['productId']] = (int) $row['cnt'];
        }
        return $counts;
    }

    /**
     * Every interaction in the system as flat rows — the raw material for
     * the collaborative-filtering user-item taste matrix. Deliberately
     * unbounded by time: a user's full history is what makes CF useful,
     * unlike the co-occurrence pass which only cares about recent sessions.
     *
     * @return array<int, array{userId: int, productId: int, type: string, value: ?int}>
     */
    public function findAllForTasteMatrix(): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('IDENTITY(i.user) AS userId, IDENTITY(i.product) AS productId, i.type AS type, i.value AS value')
            ->getQuery()
            ->getArrayResult();

        return array_map(fn ($row) => [
            'userId' => (int) $row['userId'],
            'productId' => (int) $row['productId'],
            'type' => $row['type'],
            'value' => $row['value'] !== null ? (int) $row['value'] : null,
        ], $rows);
    }

    /**
     * Find interactions by user
     */
    public function findByUser(User $user, int $limit = 100): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.user = :user')
            ->setParameter('user', $user)
            ->setMaxResults($limit)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Distinct logged-in users who viewed this product since $since — the
     * authenticated half of the product page's "X people viewing this now"
     * social-proof signal.
     */
    public function countDistinctUsersViewingSince(Product $product, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(DISTINCT i.user)')
            ->andWhere('i.product = :product')
            ->andWhere('i.type = :type')
            ->andWhere('i.createdAt >= :since')
            ->setParameter('product', $product)
            ->setParameter('type', 'view')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find interactions by product
     */
    public function findByProduct(Product $product): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.product = :product')
            ->setParameter('product', $product)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find interactions by type
     */
    public function findByType(string $type, int $limit = 50): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.type = :type')
            ->setParameter('type', $type)
            ->setMaxResults($limit)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find interactions by user and product
     */
    public function findByUserAndProduct(User $user, Product $product): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.user = :user')
            ->andWhere('i.product = :product')
            ->setParameter('user', $user)
            ->setParameter('product', $product)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find interactions by user and type
     */
    public function findByUserAndType(User $user, string $type, int $limit = 50): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.user = :user')
            ->andWhere('i.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->setMaxResults($limit)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count interactions by type
     */
    public function countByType(string $type): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.type = :type')
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Top products by interaction count for a given type.
     * Returns Product entities ordered by interaction frequency.
     */
    public function getTopProductsByType(string $type, int $limit = 10): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('p, COUNT(i.id) AS HIDDEN cnt')
            ->from(Product::class, 'p')
            ->join('p.interactions', 'i', 'WITH', 'i.type = :type')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.brand', 'b')
            ->leftJoin('p.productType', 't')
            ->addSelect('c, b, t')
            ->setParameter('type', $type)
            ->groupBy('p.id, c.id, b.id, t.id')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count interactions for one product broken down by type.
     * Returns ['view' => n, 'cart' => n, 'purchase' => n, 'rating' => n].
     */
    public function getProductInteractionCounts(Product $product): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('i.type, COUNT(i.id) AS cnt')
            ->andWhere('i.product = :product')
            ->setParameter('product', $product)
            ->groupBy('i.type')
            ->getQuery()
            ->getArrayResult();

        $counts = array_fill_keys(['view', 'cart', 'purchase', 'rating'], 0);
        foreach ($rows as $row) {
            $counts[$row['type']] = (int) $row['cnt'];
        }
        return $counts;
    }

    /**
     * Global breakdown of all interactions by type.
     */
    public function getInteractionTypeBreakdown(): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('i.type, COUNT(i.id) AS cnt')
            ->groupBy('i.type')
            ->getQuery()
            ->getArrayResult();

        $counts = array_fill_keys(['view', 'cart', 'purchase', 'rating'], 0);
        foreach ($rows as $row) {
            $counts[$row['type']] = (int) $row['cnt'];
        }
        return $counts;
    }

    /**
     * All (userId => [productId => strongest interaction type]) groups within
     * the lookback window — the authenticated-user counterpart of guest
     * session grouping, feeding the same co-occurrence pass in the
     * recommendation batch job.
     *
     * @return array<int, array<int, string>>
     */
    public function groupedByUserSince(\DateTimeImmutable $since): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('IDENTITY(i.user) AS userId, IDENTITY(i.product) AS productId, i.type AS type')
            ->andWhere('i.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getArrayResult();

        $groups = [];
        foreach ($rows as $row) {
            $groups[(int) $row['userId']][(int) $row['productId']] = $row['type'];
        }

        return $groups;
    }

    /**
     * Lightweight collaborative filtering: products interacted with by the same
     * users who also interacted with $product (co-interaction).
     */
    public function getCoInteractedProducts(Product $product, string $type = 'purchase', int $limit = 5): array
    {
        $rows = $this->createQueryBuilder('i0')
            ->select('IDENTITY(i0.user) AS userId')
            ->andWhere('i0.product = :product')
            ->andWhere('i0.type = :type')
            ->setParameter('product', $product)
            ->setParameter('type', $type)
            ->getQuery()
            ->getArrayResult();

        $userIds = array_column($rows, 'userId');
        if (empty($userIds)) {
            return [];
        }

        return $this->getEntityManager()->createQueryBuilder()
            ->select('p, COUNT(i.id) AS HIDDEN cnt')
            ->from(Product::class, 'p')
            ->join('p.interactions', 'i', 'WITH', 'i.user IN (:userIds) AND i.type = :type')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.brand', 'b')
            ->leftJoin('p.productType', 't')
            ->addSelect('c, b, t')
            ->andWhere('p != :product')
            ->setParameter('userIds', $userIds)
            ->setParameter('product', $product)
            ->setParameter('type', $type)
            ->groupBy('p.id, c.id, b.id, t.id')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
