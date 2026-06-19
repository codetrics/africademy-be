<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SubscriptionPayment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SubscriptionPayment>
 *
 * @method SubscriptionPayment|null find($id, $lockMode = null, $lockVersion = null)
 * @method SubscriptionPayment|null findOneBy(array $criteria, array $orderBy = null)
 * @method SubscriptionPayment[]    findAll()
 * @method SubscriptionPayment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SubscriptionPaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubscriptionPayment::class);
    }

    public function save(SubscriptionPayment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SubscriptionPayment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
