<?php

namespace App\Repository;

use App\Entity\Address;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Address>
 */
class AddressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Address::class);
    }

    public function save(Address $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Address $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.isDefault', 'DESC')
            ->addOrderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findDefaultForUser(User $user): ?Address
    {
        return $this->findOneBy(['user' => $user, 'isDefault' => true]);
    }

    /**
     * Unset isDefault on all user's addresses so only one can be default at a time.
     */
    public function clearDefaultForUser(User $user): void
    {
        $this->createQueryBuilder('a')
            ->update()
            ->set('a.isDefault', ':false')
            ->andWhere('a.user = :user')
            ->setParameter('false', false)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
