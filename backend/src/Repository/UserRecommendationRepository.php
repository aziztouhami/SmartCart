<?php

namespace App\Repository;

use App\Entity\User;
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
                    'INSERT INTO user_recommendation (user_id, product_id, score, source, computed_at) VALUES ' . implode(', ', $values),
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
     * @return array<int, array{productId: int, score: float}>
     */
    public function findForUser(User $user, int $limit = 20): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.product) AS productId, r.score AS score')
            ->andWhere('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.score', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(fn ($row) => ['productId' => (int) $row['productId'], 'score' => (float) $row['score']], $rows);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
