<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\RefundRequest;
use App\Enum\RefundStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<RefundRequest>
 *
 * @method RefundRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method RefundRequest|null findOneBy(array $criteria, array $orderBy = null)
 * @method RefundRequest[]    findAll()
 * @method RefundRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RefundRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefundRequest::class);
    }

    public function save(RefundRequest $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(RefundRequest $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByOrder(Order $order): ?RefundRequest
    {
        return $this->findOneBy(['order' => $order]);
    }

    public function findOneByPublicId(Ulid $publicId): ?RefundRequest
    {
        return $this->findOneBy(['publicId' => $publicId]);
    }

    public function createPendingQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('refund')
            ->leftJoin('refund.order', 'o')
            ->addSelect('o')
            ->where('refund.status = :pending')
            ->setParameter('pending', RefundStatus::Pending)
            ->orderBy('refund.createdAt', 'ASC');
    }
}

