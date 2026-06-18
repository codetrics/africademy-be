<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Certificate;
use App\Entity\Enrollment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<Certificate>
 *
 * @method Certificate|null find($id, $lockMode = null, $lockVersion = null)
 * @method Certificate|null findOneBy(array $criteria, array $orderBy = null)
 * @method Certificate[]    findAll()
 * @method Certificate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CertificateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Certificate::class);
    }

    public function save(Certificate $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Certificate $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByPublicIdAndStudent(Ulid $publicId, User $student): ?Certificate
    {
        return $this->findOneBy(['publicId' => $publicId, 'student' => $student]);
    }

    public function findOneByCredentialId(string $credentialId): ?Certificate
    {
        return $this->findOneBy(['credentialId' => $credentialId]);
    }

    public function findOneByEnrollment(Enrollment $enrollment): ?Certificate
    {
        return $this->findOneBy(['enrollment' => $enrollment]);
    }

    public function createStudentCertificatesQueryBuilder(User $student): QueryBuilder
    {
        return $this->createQueryBuilder('certificate')
            ->where('certificate.student = :student')
            ->setParameter('student', $student)
            ->orderBy('certificate.createdAt', 'DESC');
    }
}
