<?php

namespace App\Repository;

use App\Entity\Brand;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Brand>
 */
class BrandRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Brand::class);
    }

    public function save(Brand $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Brand $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findAllPaginated(int $page, int $limit): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.name', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getStats(Brand $brand): array
    {
        $productCount = $brand->getProducts()->count();

        $soldResult = $this->getEntityManager()->createQuery(
            'SELECT COALESCE(SUM(oi.quantity), 0) as soldCount, COALESCE(SUM(oi.quantity * oi.price), 0) as revenue
             FROM App\Entity\OrderItem oi
             JOIN oi.product p
             JOIN oi.order o
             WHERE p.brand = :brand
             AND o.status NOT IN (:excluded)'
        )
            ->setParameter('brand', $brand)
            ->setParameter('excluded', ['cart', 'cancelled'])
            ->getSingleResult();

        $avgRatingResult = $this->getEntityManager()->createQuery(
            'SELECT AVG(r.rating) as avgRating
             FROM App\Entity\Review r
             JOIN r.product p
             WHERE p.brand = :brand'
        )
            ->setParameter('brand', $brand)
            ->getSingleScalarResult();

        return [
            'productCount' => $productCount,
            'soldCount' => (int) $soldResult['soldCount'],
            'revenue' => (float) $soldResult['revenue'],
            'avgRating' => null !== $avgRatingResult ? round((float) $avgRatingResult, 1) : null,
        ];
    }
}
