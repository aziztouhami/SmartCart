<?php

namespace App\Repository;

use App\Entity\ColdStartRecommendation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ColdStartRecommendation>
 */
class ColdStartRecommendationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ColdStartRecommendation::class);
    }

    /**
     * @param array<int, array{productId: int, score: float}> $rows
     */
    public function replaceAll(array $rows): void
    {
        $conn = $this->getEntityManager()->getConnection();
        $conn->beginTransaction();
        try {
            $conn->executeStatement('DELETE FROM cold_start_recommendation');

            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            foreach ($rows as $row) {
                $conn->executeStatement(
                    'INSERT INTO cold_start_recommendation (product_id, score, computed_at) VALUES (?, ?, ?)',
                    [$row['productId'], $row['score'], $now]
                );
            }

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * @return int[] product ids, highest score first
     */
    public function findTopProductIds(int $limit = 12): array
    {
        return array_keys($this->findTopWithScores($limit));
    }

    /**
     * @return array<int, float> productId => score, highest first
     */
    public function findTopWithScores(int $limit = 12): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.product) AS productId, c.score AS score')
            ->orderBy('c.score', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        $scores = [];
        foreach ($rows as $row) {
            $scores[(int) $row['productId']] = (float) $row['score'];
        }

        return $scores;
    }
}
