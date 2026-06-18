<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\RefundRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
}
