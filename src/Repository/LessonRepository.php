<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<Lesson>
 *
 * @method Lesson|null find($id, $lockMode = null, $lockVersion = null)
 * @method Lesson|null findOneBy(array $criteria, array $orderBy = null)
 * @method Lesson[]    findAll()
 * @method Lesson[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LessonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lesson::class);
    }

    public function save(Lesson $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Lesson $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByPublicIdAndCourse(Ulid $publicId, Course $course): ?Lesson
    {
        return $this->findOneBy(['publicId' => $publicId, 'course' => $course]);
    }

    /**
     * @return Lesson[]
     */
    public function findByCourseOrdered(Course $course): array
    {
        return $this->findBy(['course' => $course], ['position' => 'ASC']);
    }

    public function getNextPosition(Course $course): int
    {
        $maxPosition = $this->createQueryBuilder('lesson')
            ->select('MAX(lesson.position)')
            ->where('lesson.course = :course')
            ->setParameter('course', $course)
            ->getQuery()
            ->getSingleScalarResult();

        return is_null($maxPosition) ? 0 : (int) $maxPosition + 1;
    }
}
