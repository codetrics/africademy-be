<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Entitlement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Entitlement>
 *
 * @method Entitlement|null find($id, $lockMode = null, $lockVersion = null)
 * @method Entitlement|null findOneBy(array $criteria, array $orderBy = null)
 * @method Entitlement[]    findAll()
 * @method Entitlement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EntitlementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entitlement::class);
    }

    public function save(Entitlement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Entitlement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUserAndCourse(User $user, Course $course): ?Entitlement
    {
        return $this->findOneBy(['user' => $user, 'course' => $course]);
    }
}
