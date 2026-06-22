<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PayfastWebhookEvent;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PayfastWebhookEvent>
 */
class PayfastWebhookEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PayfastWebhookEvent::class);
    }

    public function save(PayfastWebhookEvent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PayfastWebhookEvent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Events received before the given threshold, oldest first. Used by the
     * retention prune command, which removes them in a loop (no bulk DQL DELETE).
     *
     * @return PayfastWebhookEvent[]
     */
    public function findReceivedBefore(DateTime $threshold, int $limit = 500): array
    {
        return $this->createQueryBuilder('event')
            ->where('event.receivedAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('event.receivedAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
