<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Enrollment;
use App\Entity\User;
use App\Enum\CourseStatus;
use App\Enum\PaymentStatus;
use App\Exceptions\EnrollmentException;
use App\Repository\CourseRepository;
use App\Repository\EnrollmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Exception;
use Symfony\Component\Uid\Ulid;

class EnrollmentService
{
    public function __construct(
        private readonly EnrollmentRepository $enrollmentRepository,
        private readonly CourseRepository $courseRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws EnrollmentException
     */
    public function enroll(User $student, Ulid $coursePublicId): Enrollment
    {
        $course = $this->courseRepository->findOneByPublicId($coursePublicId);

        if (is_null($course)) {
            throw EnrollmentException::courseNotFound();
        }

        if ($course->getStatus() !== CourseStatus::Published) {
            throw EnrollmentException::courseNotPublished();
        }

        if (!is_null($this->enrollmentRepository->findOneByStudentAndCourse($student, $course))) {
            throw EnrollmentException::alreadyEnrolled();
        }

        $enrollment = new Enrollment();
        $enrollment->setStudent($student);
        $enrollment->setCourse($course);

        $this->entityManager->beginTransaction();
        try {
            $this->enrollmentRepository->save($enrollment, true);
            $this->entityManager->commit();
        } catch (Exception $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }

        return $enrollment;
    }

    /**
     * @throws EnrollmentException
     */
    public function getStudentEnrollment(User $student, Ulid $publicId): Enrollment
    {
        $enrollment = $this->enrollmentRepository->findOneByPublicIdAndStudent($publicId, $student);

        if (is_null($enrollment)) {
            throw EnrollmentException::enrollmentNotFound();
        }

        return $enrollment;
    }

    /**
     * Marks the enrollment as paid. Idempotent — a paid enrollment is returned unchanged.
     */
    public function markPaid(Enrollment $enrollment): Enrollment
    {
        if ($enrollment->isPaid()) {
            return $enrollment;
        }

        $enrollment->setPaymentStatus(PaymentStatus::Paid);
        $this->enrollmentRepository->save($enrollment, true);

        return $enrollment;
    }

    public function unenroll(Enrollment $enrollment): void
    {
        $this->enrollmentRepository->remove($enrollment, true);
    }

    public function createStudentEnrollmentsQueryBuilder(User $student): QueryBuilder
    {
        return $this->enrollmentRepository->createStudentEnrollmentsQueryBuilder($student);
    }
}
