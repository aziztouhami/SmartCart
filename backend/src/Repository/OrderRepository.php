<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function save(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find orders by user
     */
    public function findByUser(User $user, int $limit = 10, int $offset = 0): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.user = :user')
            ->setParameter('user', $user)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find orders by status
     */
    public function findByStatus(string $status, int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.status = :status')
            ->setParameter('status', $status)
            ->setMaxResults($limit)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent orders
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->setMaxResults($limit)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count orders by user
     */
    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
