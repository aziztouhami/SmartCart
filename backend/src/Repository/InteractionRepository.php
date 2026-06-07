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
}
