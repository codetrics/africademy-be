<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DocsUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocsUser>
 *
 * @method DocsUser|null find($id, $lockMode = null, $lockVersion = null)
 * @method DocsUser|null findOneBy(array $criteria, array $orderBy = null)
 * @method DocsUser[]    findAll()
 * @method DocsUser[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DocsUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocsUser::class);
    }

    public function save(DocsUser $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DocsUser $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUsername(string $username): ?DocsUser
    {
        return $this->findOneBy(['username' => $username]);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('docsUser')
            ->select('COUNT(docsUser.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
