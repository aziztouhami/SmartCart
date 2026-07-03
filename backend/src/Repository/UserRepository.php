<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @implements PasswordUpgraderInterface<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $hashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setPassword($hashedPassword);
        $this->save($user, true);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findByVerificationToken(string $token): ?User
    {
        return $this->findOneBy(['verificationToken' => $token]);
    }

    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countNewThisMonth(): int
    {
        $start = new \DateTimeImmutable('first day of this month 00:00:00');

        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.createdAt >= :start')
            ->setParameter('start', $start)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findAllPaginated(int $page = 1, int $limit = 20, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('u')->orderBy('u.createdAt', 'DESC');

        if ($search !== null && $search !== '') {
            $qb->andWhere('u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return User[]
     */
    public function findMarketingOptIn(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.marketingOptIn = true')
            ->andWhere('u.deletionRequestedAt IS NULL')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return User[]
     */
    public function findScheduledForDeletionBefore(\DateTimeImmutable $cutoff): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.deletionRequestedAt IS NOT NULL')
            ->andWhere('u.deletionRequestedAt <= :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();
    }

    public function countAllUsers(?string $search = null): int
    {
        $qb = $this->createQueryBuilder('u')->select('COUNT(u.id)');

        if ($search !== null && $search !== '') {
            $qb->andWhere('u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
