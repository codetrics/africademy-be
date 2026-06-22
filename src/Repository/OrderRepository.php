<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use App\Enum\OrderStatus;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<Order>
 *
 * @method Order|null find($id, $lockMode = null, $lockVersion = null)
 * @method Order|null findOneBy(array $criteria, array $orderBy = null)
 * @method Order[]    findAll()
 * @method Order[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function save(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByPublicId(Ulid $publicId): ?Order
    {
        return $this->findOneBy(['publicId' => $publicId]);
    }

    public function findOneByPublicIdAndUser(Ulid $publicId, User $user): ?Order
    {
        return $this->findOneBy(['publicId' => $publicId, 'user' => $user]);
    }

    public function createUserOrdersQueryBuilder(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.course', 'course')
            ->addSelect('course')
            ->where('o.user = :user')
            ->setParameter('user', $user)
            ->orderBy('o.createdAt', 'DESC');
    }

    /**
     * Admin order listing across all users, newest first, with optional filters.
     * Join-fetches the buyer (and profile) so the slim buyer summary needs no
     * extra queries per row.
     */
    public function createAdminOrdersQueryBuilder(
        ?OrderStatus $status,
        ?string $search,
        ?string $type,
        ?DateTime $from,
        ?DateTime $to,
    ): QueryBuilder {
        $queryBuilder = $this->createQueryBuilder('o')
            ->innerJoin('o.user', 'buyer')
            ->addSelect('buyer')
            ->leftJoin('buyer.profile', 'buyerProfile')
            ->addSelect('buyerProfile')
            ->leftJoin('o.course', 'course')
            ->addSelect('course')
            ->leftJoin('o.bundle', 'bundle')
            ->addSelect('bundle')
            ->orderBy('o.createdAt', 'DESC');

        if (!is_null($status)) {
            $queryBuilder->andWhere('o.status = :status')
                ->setParameter('status', $status);
        }

        if (!is_null($search) && $search !== '') {
            $queryBuilder->andWhere('buyer.email LIKE :search OR buyerProfile.firstName LIKE :search OR buyerProfile.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($type === 'course') {
            $queryBuilder->andWhere('o.course IS NOT NULL');
        } elseif ($type === 'bundle') {
            $queryBuilder->andWhere('o.bundle IS NOT NULL');
        }

        if (!is_null($from)) {
            $queryBuilder->andWhere('o.createdAt >= :from')
                ->setParameter('from', $from);
        }

        if (!is_null($to)) {
            $queryBuilder->andWhere('o.createdAt <= :to')
                ->setParameter('to', $to);
        }

        return $queryBuilder;
    }

    /**
     * Order counts keyed by status value (pending/paid/cancelled/refunded),
     * each defaulting to 0.
     *
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        $counts = [
            OrderStatus::Pending->value => 0,
            OrderStatus::Paid->value => 0,
            OrderStatus::Cancelled->value => 0,
            OrderStatus::Refunded->value => 0,
        ];

        $rows = $this->createQueryBuilder('o')
            ->select('o.status AS status, COUNT(o.id) AS total')
            ->groupBy('o.status')
            ->getQuery()
            ->getResult();

        foreach ($rows as $row) {
            $status = $row['status'] instanceof OrderStatus ? $row['status']->value : (string) $row['status'];
            $counts[$status] = (int) $row['total'];
        }

        return $counts;
    }
}
