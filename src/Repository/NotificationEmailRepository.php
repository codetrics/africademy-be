<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NotificationEmail;
use App\Enum\NotificationStatus;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationEmail>
 *
 * @method NotificationEmail|null find($id, $lockMode = null, $lockVersion = null)
 * @method NotificationEmail|null findOneBy(array $criteria, array $orderBy = null)
 * @method NotificationEmail[]    findAll()
 * @method NotificationEmail[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationEmailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationEmail::class);
    }

    public function save(NotificationEmail $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(NotificationEmail $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Pending notifications whose send time has arrived.
     *
     * @return NotificationEmail[]
     */
    public function findPendingDue(DateTime $now, int $limit = 50): array
    {
        return $this->createQueryBuilder('notification')
            ->where('notification.status = :pending')
            ->andWhere('notification.sendAt <= :now')
            ->setParameter('pending', NotificationStatus::Pending)
            ->setParameter('now', $now)
            ->orderBy('notification.sendAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
