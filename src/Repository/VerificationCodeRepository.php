<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\VerificationCode;
use App\Enum\VerificationPurpose;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VerificationCode>
 *
 * @method VerificationCode|null find($id, $lockMode = null, $lockVersion = null)
 * @method VerificationCode|null findOneBy(array $criteria, array $orderBy = null)
 * @method VerificationCode[]    findAll()
 * @method VerificationCode[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VerificationCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VerificationCode::class);
    }

    public function save(VerificationCode $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(VerificationCode $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findLatestActive(User $user, VerificationPurpose $purpose): ?VerificationCode
    {
        return $this->createQueryBuilder('code')
            ->where('code.user = :user')
            ->andWhere('code.purpose = :purpose')
            ->andWhere('code.usedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('purpose', $purpose)
            ->orderBy('code.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Marks all of the user's unused codes for a purpose as consumed, so a freshly
     * issued code is the only valid one.
     */
    public function invalidateActive(User $user, VerificationPurpose $purpose): void
    {
        $this->createQueryBuilder('code')
            ->update()
            ->set('code.usedAt', ':now')
            ->where('code.user = :user')
            ->andWhere('code.purpose = :purpose')
            ->andWhere('code.usedAt IS NULL')
            ->setParameter('now', new DateTime())
            ->setParameter('user', $user)
            ->setParameter('purpose', $purpose)
            ->getQuery()
            ->execute();
    }
}
