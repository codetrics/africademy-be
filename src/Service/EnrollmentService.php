<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\User;
use App\Enum\CourseStatus;
use App\Enum\EntitlementSource;
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
        private readonly AccessService $accessService,
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

        // Free courses grant access on enrollment; paid courses require a prior purchase.
        if ($course->isFree()) {
            $this->accessService->grant($student, $course, EntitlementSource::Free);
        } elseif (!$this->accessService->hasAccess($student, $course)) {
            throw EnrollmentException::purchaseRequired();
        }

        $enrollment = new Enrollment();
        $enrollment->setStudent($student);
        $enrollment->setCourse($course);
        $course->adjustEnrollmentCount(1);

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

    public function unenroll(Enrollment $enrollment): void
    {
        $enrollment->getCourse()->adjustEnrollmentCount(-1);
        $this->enrollmentRepository->remove($enrollment, true);
    }

    /**
     * Ensures the student has an enrollment for the course, creating one if
     * needed. Used after a purchase grants access — no access/published checks.
     */
    public function ensureEnrolled(User $student, Course $course): Enrollment
    {
        $existing = $this->enrollmentRepository->findOneByStudentAndCourse($student, $course);

        if ($existing instanceof Enrollment) {
            return $existing;
        }

        $enrollment = new Enrollment();
        $enrollment->setStudent($student);
        $enrollment->setCourse($course);
        $course->adjustEnrollmentCount(1);
        $this->enrollmentRepository->save($enrollment, true);

        return $enrollment;
    }

    public function createStudentEnrollmentsQueryBuilder(User $student): QueryBuilder
    {
        return $this->enrollmentRepository->createStudentEnrollmentsQueryBuilder($student);
    }
}
