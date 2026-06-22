<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\SubscriptionStatus;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<Subscription>
 *
 * @method Subscription|null find($id, $lockMode = null, $lockVersion = null)
 * @method Subscription|null findOneBy(array $criteria, array $orderBy = null)
 * @method Subscription[]    findAll()
 * @method Subscription[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    public function save(Subscription $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Subscription $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findActiveByUser(User $user): ?Subscription
    {
        return $this->findOneBy(['user' => $user, 'status' => SubscriptionStatus::Active]);
    }

    /**
     * An Active subscription whose current period has not yet ended — the gate
     * for subscription-based access (an Active row past its period that the
     * billing job hasn't expired yet must NOT grant free access).
     */
    public function findActiveByUserWithinPeriod(User $user): ?Subscription
    {
        return $this->createQueryBuilder('subscription')
            ->where('subscription.user = :user')
            ->andWhere('subscription.status = :status')
            ->andWhere('subscription.currentPeriodEnd >= :now')
            ->setParameter('user', $user)
            ->setParameter('status', SubscriptionStatus::Active)
            ->setParameter('now', new DateTime())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findCurrentByUser(User $user): ?Subscription
    {
        return $this->findOneBy(['user' => $user], ['id' => 'DESC']);
    }

    public function findOneByPublicIdAndUser(Ulid $publicId, User $user): ?Subscription
    {
        return $this->findOneBy(['publicId' => $publicId, 'user' => $user]);
    }

    /**
     * Active or past-due subscriptions whose current period has ended — to be
     * renewed, retried, or expired by the billing job.
     *
     * @return Subscription[]
     */
    public function findDueForRenewal(DateTime $now): array
    {
        return $this->createQueryBuilder('subscription')
            ->where('subscription.status IN (:dueStatuses)')
            ->andWhere('subscription.currentPeriodEnd <= :now')
            ->setParameter('dueStatuses', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}
