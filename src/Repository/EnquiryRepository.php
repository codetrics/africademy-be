<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Enquiry;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Enquiry>
 *
 * @method Enquiry|null find($id, $lockMode = null, $lockVersion = null)
 * @method Enquiry|null findOneBy(array $criteria, array $orderBy = null)
 * @method Enquiry[]    findAll()
 * @method Enquiry[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EnquiryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Enquiry::class);
    }

    public function save(Enquiry $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Enquiry $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function createAdminQueryBuilder(?DateTime $from, ?DateTime $to): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('enquiry')
            ->orderBy('enquiry.createdAt', 'DESC');

        if (!is_null($from)) {
            $queryBuilder->andWhere('enquiry.createdAt >= :from')
                ->setParameter('from', $from);
        }

        if (!is_null($to)) {
            $queryBuilder->andWhere('enquiry.createdAt <= :to')
                ->setParameter('to', $to);
        }

        return $queryBuilder;
    }
}
