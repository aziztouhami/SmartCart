<?php

namespace App\Repository;

use App\Entity\UserRecommendation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserRecommendation>
 */
class UserRecommendationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserRecommendation::class);
    }

    /**
     * Atomically swaps in a freshly computed recommendation set for every
     * user — truncate + bulk insert, same pattern as ProductRelation.
     *
     * @param array<int, array{userId: int, productId: int, score: float, source: string}> $rows
     */
    public function replaceAll(array $rows): void
    {
        $conn = $this->getEntityManager()->getConnection();
        $conn->beginTransaction();
        try {
            $conn->executeStatement('DELETE FROM user_recommendation');

            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            foreach (array_chunk($rows, 500) as $chunk) {
                $values = [];
                $params = [];
                foreach ($chunk as $row) {
                    $values[] = '(?, ?, ?, ?, ?)';
                    $params[] = $row['userId'];
                    $params[] = $row['productId'];
                    $params[] = $row['score'];
                    $params[] = $row['source'];
                    $params[] = $now;
                }
                $conn->executeStatement(
                    'INSERT INTO user_recommendation (user_id, product_id, score, source, computed_at) VALUES '.implode(', ', $values),
                    $params
                );
            }

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
