<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Enquiry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
}
