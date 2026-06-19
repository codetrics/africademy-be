<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Coupon;
use App\Entity\CouponRedemption;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CouponRedemption>
 *
 * @method CouponRedemption|null find($id, $lockMode = null, $lockVersion = null)
 * @method CouponRedemption|null findOneBy(array $criteria, array $orderBy = null)
 * @method CouponRedemption[]    findAll()
 * @method CouponRedemption[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CouponRedemptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CouponRedemption::class);
    }

    public function save(CouponRedemption $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CouponRedemption $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByCouponAndUser(Coupon $coupon, User $user): ?CouponRedemption
    {
        return $this->findOneBy(['coupon' => $coupon, 'user' => $user]);
    }
}
