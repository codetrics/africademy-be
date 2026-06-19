<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UserLogType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserLogType>
 *
 * @method UserLogType|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserLogType|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserLogType[]    findAll()
 * @method UserLogType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserLogTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserLogType::class);
    }

    public function save(UserLogType $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserLogType $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneBySlug(string $slug): ?UserLogType
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
