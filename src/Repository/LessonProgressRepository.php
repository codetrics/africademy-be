<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Enrollment;
use App\Entity\Lesson;
use App\Entity\LessonProgress;
use App\Enum\LessonStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LessonProgress>
 *
 * @method LessonProgress|null find($id, $lockMode = null, $lockVersion = null)
 * @method LessonProgress|null findOneBy(array $criteria, array $orderBy = null)
 * @method LessonProgress[]    findAll()
 * @method LessonProgress[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LessonProgressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LessonProgress::class);
    }

    public function save(LessonProgress $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(LessonProgress $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByEnrollmentAndLesson(Enrollment $enrollment, Lesson $lesson): ?LessonProgress
    {
        return $this->findOneBy(['enrollment' => $enrollment, 'lesson' => $lesson]);
    }

    /**
     * Internal lesson ids (PK) of the published lessons this enrollment has completed.
     *
     * @return int[]
     */
    public function findCompletedPublishedLessonIds(Enrollment $enrollment): array
    {
        $rows = $this->createQueryBuilder('progress')
            ->select('IDENTITY(progress.lesson) AS lessonId')
            ->innerJoin('progress.lesson', 'lesson')
            ->where('progress.enrollment = :enrollment')
            ->andWhere('lesson.status = :published')
            ->setParameter('enrollment', $enrollment)
            ->setParameter('published', LessonStatus::Published)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['lessonId'], $rows);
    }
}
