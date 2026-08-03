<?php

namespace App\Repository;

use App\Entity\Brand;
use App\Entity\Product;
use App\Entity\Promotion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Promotion>
 */
class PromotionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Promotion::class);
    }

    public function save(Promotion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Promotion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    private function activeQueryBuilder(\DateTimeImmutable $now)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.startDate <= :now')
            ->andWhere('p.endDate IS NULL OR p.endDate >= :now')
            ->setParameter('now', $now);
    }

    /**
     * All currently-active promotions (any scope), newest first.
     *
     * @return Promotion[]
     */
    public function findActive(): array
    {
        return $this->activeQueryBuilder(new \DateTimeImmutable())
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Build a map of productId => active Promotion for the given products,
     * resolving precedence (product-specific > brand-wide > store-wide).
     *
     * @param Product[] $products
     *
     * @return array<int, Promotion>
     */
    public function findActiveForProducts(array $products): array
    {
        if (empty($products)) {
            return [];
        }

        $active = $this->findActive();
        if (empty($active)) {
            return [];
        }

        $byProduct = [];
        $byBrand = [];
        $storeWide = null;
        foreach ($active as $promo) {
            if (Promotion::TYPE_PRODUCT === $promo->getType() && $promo->getProduct()) {
                $byProduct[$promo->getProduct()->getId()] ??= $promo;
            } elseif (Promotion::TYPE_BRAND === $promo->getType() && $promo->getBrand()) {
                $byBrand[$promo->getBrand()->getId()] ??= $promo;
            } elseif (Promotion::TYPE_ALL === $promo->getType()) {
                $storeWide ??= $promo;
            }
        }

        $result = [];
        foreach ($products as $product) {
            $promo = $byProduct[$product->getId()]
                ?? ($product->getBrand() ? ($byBrand[$product->getBrand()->getId()] ?? null) : null)
                ?? $storeWide;
            if ($promo) {
                $result[$product->getId()] = $promo;
            }
        }

        return $result;
    }

    public function findActiveForProduct(Product $product): ?Promotion
    {
        $map = $this->findActiveForProducts([$product]);

        return $map[$product->getId()] ?? null;
    }

    public function findAllPaginated(int $page = 1, int $limit = 20): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
