<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SubscriptionPlan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<SubscriptionPlan>
 *
 * @method SubscriptionPlan|null find($id, $lockMode = null, $lockVersion = null)
 * @method SubscriptionPlan|null findOneBy(array $criteria, array $orderBy = null)
 * @method SubscriptionPlan[]    findAll()
 * @method SubscriptionPlan[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SubscriptionPlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubscriptionPlan::class);
    }

    public function save(SubscriptionPlan $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SubscriptionPlan $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneBySlug(string $slug): ?SubscriptionPlan
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function findOneByPublicId(Ulid $publicId): ?SubscriptionPlan
    {
        return $this->findOneBy(['publicId' => $publicId]);
    }

    /**
     * @return SubscriptionPlan[]
     */
    public function findActive(): array
    {
        return $this->findBy(['active' => true], ['id' => 'ASC']);
    }
}
