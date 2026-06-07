<?php

namespace App\Repository;

use App\Entity\Review;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    public function save(Review $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Review $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find reviews by product
     */
    public function findByProduct(Product $product, int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.product = :product')
            ->setParameter('product', $product)
            ->setMaxResults($limit)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find reviews by user
     */
    public function findByUser(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->setParameter('user', $user)
            ->setMaxResults($limit)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculate average rating for a product
     */
    public function getAverageRating(Product $product): ?float
    {
        return (float) $this->createQueryBuilder('r')
            ->select('AVG(r.rating)')
            ->andWhere('r.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count reviews for a product
     */
    public function countByProduct(Product $product): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find reviews by rating
     */
    public function findByRating(int $rating, int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.rating = :rating')
            ->setParameter('rating', $rating)
            ->setMaxResults($limit)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
