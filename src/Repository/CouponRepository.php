<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Coupon;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<Coupon>
 *
 * @method Coupon|null find($id, $lockMode = null, $lockVersion = null)
 * @method Coupon|null findOneBy(array $criteria, array $orderBy = null)
 * @method Coupon[]    findAll()
 * @method Coupon[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CouponRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Coupon::class);
    }

    public function save(Coupon $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Coupon $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByCode(string $code): ?Coupon
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function findOneByPublicId(Ulid $publicId): ?Coupon
    {
        return $this->findOneBy(['publicId' => $publicId]);
    }

    public function createListQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('coupon')->orderBy('coupon.createdAt', 'DESC');
    }

    public function incrementRedemptionCount(Coupon $coupon): void
    {
        $coupon->incrementRedemptionCount();
        $this->getEntityManager()->flush();
    }
}
