<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PaymentMethod;
use App\Entity\User;
use App\Enum\PaymentMethodStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<PaymentMethod>
 *
 * @method PaymentMethod|null find($id, $lockMode = null, $lockVersion = null)
 * @method PaymentMethod|null findOneBy(array $criteria, array $orderBy = null)
 * @method PaymentMethod[]    findAll()
 * @method PaymentMethod[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PaymentMethodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentMethod::class);
    }

    public function save(PaymentMethod $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PaymentMethod $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByPublicIdAndUser(Ulid $publicId, User $user): ?PaymentMethod
    {
        return $this->findOneBy(['publicId' => $publicId, 'user' => $user, 'status' => PaymentMethodStatus::Active]);
    }

    /**
     * @return PaymentMethod[]
     */
    public function findActiveByUser(User $user): array
    {
        return $this->findBy(
            ['user' => $user, 'status' => PaymentMethodStatus::Active],
            ['isDefault' => 'DESC', 'id' => 'DESC'],
        );
    }

    public function findDefaultByUser(User $user): ?PaymentMethod
    {
        return $this->findOneBy(['user' => $user, 'status' => PaymentMethodStatus::Active, 'isDefault' => true]);
    }

    public function countActiveByUser(User $user): int
    {
        return $this->count(['user' => $user, 'status' => PaymentMethodStatus::Active]);
    }
}
