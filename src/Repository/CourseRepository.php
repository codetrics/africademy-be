<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Course;
use App\Entity\User;
use App\Enum\CourseLevel;
use App\Enum\CourseStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<Course>
 *
 * @method Course|null find($id, $lockMode = null, $lockVersion = null)
 * @method Course|null findOneBy(array $criteria, array $orderBy = null)
 * @method Course[]    findAll()
 * @method Course[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CourseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Course::class);
    }

    public function save(Course $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Course $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByPublicId(Ulid $publicId): ?Course
    {
        return $this->findOneBy(['publicId' => $publicId]);
    }

    public function slugExists(string $slug): bool
    {
        return (int) $this->createQueryBuilder('course')
            ->select('COUNT(course.id)')
            ->where('course.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Catalog query: published courses, plus the viewer's own (any status) when an owner is supplied.
     */
    public function createCatalogQueryBuilder(
        ?Category $category,
        ?CourseLevel $level,
        ?string $search,
        ?User $owner,
    ): QueryBuilder {
        $queryBuilder = $this->createQueryBuilder('course')
            ->leftJoin('course.category', 'category')
            ->addSelect('category');

        if ($owner instanceof User) {
            $queryBuilder
                ->where('course.status = :published OR course.owner = :owner')
                ->setParameter('owner', $owner);
        } else {
            $queryBuilder->where('course.status = :published');
        }

        $queryBuilder->setParameter('published', CourseStatus::Published);

        if ($category instanceof Category) {
            $queryBuilder->andWhere('course.category = :category')->setParameter('category', $category);
        }

        if ($level instanceof CourseLevel) {
            $queryBuilder->andWhere('course.level = :level')->setParameter('level', $level);
        }

        if (!is_null($search) && $search !== '') {
            $queryBuilder->andWhere('course.title LIKE :search')->setParameter('search', '%' . $search . '%');
        }

        return $queryBuilder->orderBy('course.createdAt', 'DESC');
    }
}
