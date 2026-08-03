<?php

namespace App\Repository;

use App\Entity\ProductRelation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductRelation>
 */
class ProductRelationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductRelation::class);
    }

    /**
     * Atomically swaps in a freshly computed relation table. Truncate +
     * bulk insert is simplest and fast enough at catalog scale — this is
     * the batch job's write path, never on the request path.
     *
     * @param array<int, array{productId: int, relatedProductId: int, score: float, type: string}> $rows
     */
    public function replaceAll(array $rows): void
    {
        $conn = $this->getEntityManager()->getConnection();
        $conn->beginTransaction();
        try {
            $conn->executeStatement('DELETE FROM product_relation');

            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            foreach (array_chunk($rows, 500) as $chunk) {
                $values = [];
                $params = [];
                foreach ($chunk as $row) {
                    $values[] = '(?, ?, ?, ?, ?)';
                    $params[] = $row['productId'];
                    $params[] = $row['relatedProductId'];
                    $params[] = $row['score'];
                    $params[] = $row['type'];
                    $params[] = $now;
                }
                $conn->executeStatement(
                    'INSERT INTO product_relation (product_id, related_product_id, score, type, computed_at) VALUES '.implode(', ', $values),
                    $params
                );
            }

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * Raw relation rows for a set of seed products, capped per seed —
     * the live serving query (single indexed lookup, no computation).
     *
     * @param int[] $productIds
     *
     * @return array<int, array{productId: int, relatedProductId: int, score: float}>
     */
    public function findRelationsForProducts(array $productIds, int $perProductLimit = 20): array
    {
        if (empty($productIds)) {
            return [];
        }

        $rows = $this->getEntityManager()->createQuery(
            'SELECT IDENTITY(r.product) AS productId, IDENTITY(r.relatedProduct) AS relatedProductId, r.score AS score
             FROM App\Entity\ProductRelation r
             WHERE r.product IN (:productIds)
             ORDER BY r.score DESC'
        )->setParameter('productIds', $productIds)->getArrayResult();

        $perProduct = [];
        $result = [];
        foreach ($rows as $row) {
            $pid = (int) $row['productId'];
            $perProduct[$pid] ??= 0;
            if ($perProduct[$pid] >= $perProductLimit) {
                continue;
            }
            ++$perProduct[$pid];
            $result[] = [
                'productId' => $pid,
                'relatedProductId' => (int) $row['relatedProductId'],
                'score' => (float) $row['score'],
            ];
        }

        return $result;
    }

    /**
     * Direct "what goes with this one product" lookup for a product detail
     * page — a single indexed read against (product_id, type, score),
     * no session/recency stitching needed.
     *
     * Pass $minScore > 0 to exclude low-confidence relations (e.g. filter
     * out coincidental co-occurrence noise for the "Frequently Bought
     * Together" section so only meaningful pairings are shown).
     *
     * @return int[] related product ids, most relevant first
     */
    public function findTopForProduct(int $productId, string $type, int $limit, float $minScore = 0.0): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.relatedProduct) AS relatedProductId')
            ->andWhere('r.product = :productId')
            ->andWhere('r.type = :type')
            ->setParameter('productId', $productId)
            ->setParameter('type', $type)
            ->orderBy('r.score', 'DESC')
            ->setMaxResults($limit);

        if ($minScore > 0.0) {
            $qb->andWhere('r.score >= :minScore')->setParameter('minScore', $minScore);
        }

        return array_map(
            fn ($row) => (int) $row['relatedProductId'],
            $qb->getQuery()->getArrayResult()
        );
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
