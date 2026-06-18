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

    public function findCurrentByUser(User $user): ?Subscription
    {
        return $this->findOneBy(['user' => $user], ['id' => 'DESC']);
    }

    public function findOneByPublicIdAndUser(Ulid $publicId, User $user): ?Subscription
    {
        return $this->findOneBy(['publicId' => $publicId, 'user' => $user]);
    }

    /**
     * Active subscriptions whose current period has ended (renew or expire).
     *
     * @return Subscription[]
     */
    public function findDueForRenewal(DateTime $now): array
    {
        return $this->createQueryBuilder('subscription')
            ->where('subscription.status = :active')
            ->andWhere('subscription.currentPeriodEnd <= :now')
            ->setParameter('active', SubscriptionStatus::Active)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}
