<?php

namespace App\Repository;

use App\Entity\CategorySeasonalScore;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CategorySeasonalScore>
 */
class CategorySeasonalScoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategorySeasonalScore::class);
    }

    /**
     * @param array<int, array{categoryId: int, month: int, score: float}> $rows
     */
    public function replaceAll(array $rows): void
    {
        $conn = $this->getEntityManager()->getConnection();
        $conn->beginTransaction();
        try {
            $conn->executeStatement('DELETE FROM category_seasonal_score');

            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            foreach ($rows as $row) {
                $conn->executeStatement(
                    'INSERT INTO category_seasonal_score (category_id, month, score, computed_at) VALUES (?, ?, ?, ?)',
                    [$row['categoryId'], $row['month'], $row['score'], $now]
                );
            }

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * For future integration: every learned score, grouped for an O(1)
     * lookup by a SeasonalBoostService variant — categoryId => [month => score].
     *
     * @return array<int, array<int, float>>
     */
    public function findAllAsMap(): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.category) AS categoryId, s.month AS month, s.score AS score')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['categoryId']][(int) $row['month']] = (float) $row['score'];
        }
        return $map;
    }
}
