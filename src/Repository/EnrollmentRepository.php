<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<Enrollment>
 *
 * @method Enrollment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Enrollment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Enrollment[]    findAll()
 * @method Enrollment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EnrollmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Enrollment::class);
    }

    public function save(Enrollment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Enrollment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByPublicIdAndStudent(Ulid $publicId, User $student): ?Enrollment
    {
        return $this->findOneBy(['publicId' => $publicId, 'student' => $student]);
    }

    public function findOneByStudentAndCourse(User $student, Course $course): ?Enrollment
    {
        return $this->findOneBy(['student' => $student, 'course' => $course]);
    }

    public function createStudentEnrollmentsQueryBuilder(User $student): QueryBuilder
    {
        return $this->createQueryBuilder('enrollment')
            ->leftJoin('enrollment.course', 'course')
            ->addSelect('course')
            ->where('enrollment.student = :student')
            ->setParameter('student', $student)
            ->orderBy('enrollment.createdAt', 'DESC');
    }
}
