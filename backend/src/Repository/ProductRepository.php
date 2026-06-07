<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function save(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findBySlug(string $slug): ?Product
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Find products by category
     */
    public function findByCategory(int $categoryId, int $limit = 10, int $offset = 0): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.category = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search products by name or description
     */
    public function search(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.name LIKE :query OR p.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults($limit)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find products in stock
     */
    public function findInStock(int $limit = 10, int $offset = 0): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.stock > 0')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
