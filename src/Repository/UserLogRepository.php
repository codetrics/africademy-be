<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UserLog;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserLog>
 *
 * @method UserLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserLog[]    findAll()
 * @method UserLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserLog::class);
    }

    public function save(UserLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Admin activity feed, newest first, filtered by log type slug, a free-text
     * search across username and message, and a created-at window.
     */
    public function createFeedQueryBuilder(?string $typeSlug, ?string $search, ?DateTime $from, ?DateTime $to): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('userLog')
            ->innerJoin('userLog.userLogType', 'logType')
            ->addSelect('logType')
            ->orderBy('userLog.createdAt', 'DESC');

        if (!is_null($typeSlug) && $typeSlug !== '') {
            $queryBuilder->andWhere('logType.slug = :typeSlug')
                ->setParameter('typeSlug', $typeSlug);
        }

        if (!is_null($search) && $search !== '') {
            $queryBuilder->andWhere('userLog.username LIKE :search OR userLog.message LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if (!is_null($from)) {
            $queryBuilder->andWhere('userLog.createdAt >= :from')
                ->setParameter('from', $from);
        }

        if (!is_null($to)) {
            $queryBuilder->andWhere('userLog.createdAt <= :to')
                ->setParameter('to', $to);
        }

        return $queryBuilder;
    }

    /**
     * @return UserLog[]
     */
    public function findRecentByUsername(string $username, int $limit): array
    {
        return $this->createQueryBuilder('userLog')
            ->where('userLog.username = :username')
            ->setParameter('username', $username)
            ->orderBy('userLog.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
