<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Bundle;
use App\Entity\User;
use App\Enum\BundleStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<Bundle>
 *
 * @method Bundle|null find($id, $lockMode = null, $lockVersion = null)
 * @method Bundle|null findOneBy(array $criteria, array $orderBy = null)
 * @method Bundle[]    findAll()
 * @method Bundle[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BundleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bundle::class);
    }

    public function save(Bundle $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Bundle $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByPublicId(Ulid $publicId): ?Bundle
    {
        return $this->findOneBy(['publicId' => $publicId]);
    }

    public function slugExists(string $slug): bool
    {
        return (int) $this->createQueryBuilder('bundle')
            ->select('COUNT(bundle.id)')
            ->where('bundle.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Published bundles, plus the viewer's own (any status) when an owner is supplied.
     */
    public function createCatalogQueryBuilder(?User $owner): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('bundle');

        if ($owner instanceof User) {
            $queryBuilder
                ->where('bundle.status = :published OR bundle.owner = :owner')
                ->setParameter('owner', $owner);
        } else {
            $queryBuilder->where('bundle.status = :published');
        }

        return $queryBuilder
            ->setParameter('published', BundleStatus::Published)
            ->orderBy('bundle.createdAt', 'DESC');
    }
}
