<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<Review>
 *
 * @method Review|null find($id, $lockMode = null, $lockVersion = null)
 * @method Review|null findOneBy(array $criteria, array $orderBy = null)
 * @method Review[]    findAll()
 * @method Review[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    public function save(Review $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Review $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByPublicId(Ulid $publicId): ?Review
    {
        return $this->findOneBy(['publicId' => $publicId]);
    }

    public function findOneByCourseAndStudent(Course $course, User $student): ?Review
    {
        return $this->findOneBy(['course' => $course, 'student' => $student]);
    }

    public function createCourseReviewsQueryBuilder(Course $course): QueryBuilder
    {
        return $this->createQueryBuilder('review')
            ->where('review.course = :course')
            ->setParameter('course', $course)
            ->orderBy('review.createdAt', 'DESC');
    }

    /**
     * @return array{count: int, average: float}
     */
    public function ratingStats(Course $course): array
    {
        $row = $this->createQueryBuilder('review')
            ->select('COUNT(review.id) AS count', 'COALESCE(AVG(review.rating), 0) AS average')
            ->where('review.course = :course')
            ->setParameter('course', $course)
            ->getQuery()
            ->getSingleResult();

        return [
            'count' => (int) $row['count'],
            'average' => round((float) $row['average'], 2),
        ];
    }
}
