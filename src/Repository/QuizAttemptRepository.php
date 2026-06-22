<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Quiz;
use App\Entity\QuizAttempt;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuizAttempt>
 *
 * @method QuizAttempt|null find($id, $lockMode = null, $lockVersion = null)
 * @method QuizAttempt|null findOneBy(array $criteria, array $orderBy = null)
 * @method QuizAttempt[]    findAll()
 * @method QuizAttempt[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class QuizAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuizAttempt::class);
    }

    public function save(QuizAttempt $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(QuizAttempt $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return QuizAttempt[]
     */
    public function findByQuiz(Quiz $quiz): array
    {
        return $this->findBy(['quiz' => $quiz]);
    }

    public function hasPassingAttempt(User $student, Quiz $quiz): bool
    {
        $count = $this->createQueryBuilder('attempt')
            ->select('COUNT(attempt.id)')
            ->where('attempt.student = :student')
            ->andWhere('attempt.quiz = :quiz')
            ->andWhere('attempt.passed = true')
            ->setParameter('student', $student)
            ->setParameter('quiz', $quiz)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    public function createStudentAttemptsQueryBuilder(User $student, Quiz $quiz): QueryBuilder
    {
        return $this->createQueryBuilder('attempt')
            ->where('attempt.student = :student')
            ->andWhere('attempt.quiz = :quiz')
            ->setParameter('student', $student)
            ->setParameter('quiz', $quiz)
            ->orderBy('attempt.createdAt', 'DESC');
    }
}
