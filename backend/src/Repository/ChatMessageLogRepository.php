<?php

namespace App\Repository;

use App\Entity\ChatMessageLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChatMessageLog>
 */
class ChatMessageLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatMessageLog::class);
    }

    public function save(ChatMessageLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * How many *user* messages this session has sent since $since — the
     * basis for the chatbot's simple per-session rate limit.
     */
    public function countUserMessagesSince(string $sessionId, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.sessionId = :sessionId')
            ->andWhere('m.role = :role')
            ->andWhere('m.createdAt >= :since')
            ->setParameter('sessionId', $sessionId)
            ->setParameter('role', 'user')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Recent turns for this session, oldest first — short-term context for
     * the prompt when the client doesn't already carry its own history.
     *
     * @return ChatMessageLog[]
     */
    public function findRecentBySession(string $sessionId, int $limit = 6): array
    {
        $rows = $this->createQueryBuilder('m')
            ->andWhere('m.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_reverse($rows);
    }

    /**
     * Drop log rows past the retention window — same reasoning as
     * GuestEventRepository::pruneOlderThan, conversations are anonymous but
     * shouldn't grow unbounded.
     */
    public function pruneOlderThan(\DateTimeImmutable $cutoff): int
    {
        return (int) $this->createQueryBuilder('m')
            ->delete()
            ->andWhere('m.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}
