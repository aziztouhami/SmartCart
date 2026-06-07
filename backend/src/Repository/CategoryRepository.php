<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function save(Category $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Category $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findBySlug(string $slug): ?Category
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Find root categories (with no parent)
     */
    public function findRoots(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.parent IS NULL')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find child categories of a parent
     */
    public function findChildren(Category $parent): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.parent = :parent')
            ->setParameter('parent', $parent)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
