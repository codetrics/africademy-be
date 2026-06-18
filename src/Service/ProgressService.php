<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Enrollment;
use App\Entity\Lesson;
use App\Entity\LessonProgress;
use App\Entity\User;
use App\Enum\EnrollmentStatus;
use App\Enum\LessonState;
use App\Enum\LessonStatus;
use App\Exceptions\EnrollmentException;
use App\Repository\CourseRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\LessonProgressRepository;
use App\Repository\LessonRepository;
use App\Service\ReturnType\ProgressReturnType;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Uid\Ulid;

class ProgressService
{
    public function __construct(
        private readonly CourseRepository $courseRepository,
        private readonly LessonRepository $lessonRepository,
        private readonly EnrollmentRepository $enrollmentRepository,
        private readonly LessonProgressRepository $lessonProgressRepository,
        private readonly AccessService $accessService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws EnrollmentException
     */
    public function markComplete(User $student, Ulid $coursePublicId, Ulid $lessonPublicId): ProgressReturnType
    {
        $enrollment = $this->resolveAccessibleEnrollment($student, $coursePublicId);
        $lesson = $this->resolvePublishedLesson($enrollment, $lessonPublicId);

        $this->entityManager->beginTransaction();
        try {
            if (is_null($this->lessonProgressRepository->findOneByEnrollmentAndLesson($enrollment, $lesson))) {
                $progress = new LessonProgress();
                $progress->setEnrollment($enrollment);
                $progress->setLesson($lesson);
                $this->lessonProgressRepository->save($progress, true);
            }

            $this->recomputeStatus($enrollment);
            $this->entityManager->commit();
        } catch (Exception $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }

        return $this->buildProgress($enrollment);
    }

    /**
     * @throws EnrollmentException
     */
    public function unmarkComplete(User $student, Ulid $coursePublicId, Ulid $lessonPublicId): ProgressReturnType
    {
        $enrollment = $this->resolveAccessibleEnrollment($student, $coursePublicId);
        $lesson = $this->resolvePublishedLesson($enrollment, $lessonPublicId);

        $this->entityManager->beginTransaction();
        try {
            $progress = $this->lessonProgressRepository->findOneByEnrollmentAndLesson($enrollment, $lesson);
            if (!is_null($progress)) {
                $this->lessonProgressRepository->remove($progress, true);
            }

            $this->recomputeStatus($enrollment);
            $this->entityManager->commit();
        } catch (Exception $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }

        return $this->buildProgress($enrollment);
    }

    /**
     * Ordered lessons with this student's computed state, plus overall progress.
     *
     * @throws EnrollmentException
     *
     * @return array{progress: ProgressReturnType, lessons: array<int, array{lesson: Lesson, state: string}>}
     */
    public function getLearnView(User $student, Ulid $coursePublicId): array
    {
        $enrollment = $this->resolveAccessibleEnrollment($student, $coursePublicId);
        $publishedLessons = $this->lessonRepository->findPublishedByCourseOrdered($enrollment->getCourse());
        $completedIds = array_flip($this->lessonProgressRepository->findCompletedPublishedLessonIds($enrollment));
        $currentLessonId = $this->firstIncompleteLessonId($publishedLessons, $completedIds);

        $lessons = [];
        foreach ($publishedLessons as $lesson) {
            if (array_key_exists($lesson->getId(), $completedIds)) {
                $state = LessonState::Done;
            } elseif ($lesson->getId() === $currentLessonId) {
                $state = LessonState::Current;
            } else {
                $state = LessonState::Upcoming;
            }

            $lessons[] = ['lesson' => $lesson, 'state' => $state->value];
        }

        return [
            'progress' => $this->buildProgress($enrollment),
            'lessons' => $lessons,
        ];
    }

    public function buildProgress(Enrollment $enrollment): ProgressReturnType
    {
        $publishedLessons = $this->lessonRepository->findPublishedByCourseOrdered($enrollment->getCourse());
        $completedIds = array_flip($this->lessonProgressRepository->findCompletedPublishedLessonIds($enrollment));

        $totalCount = count($publishedLessons);
        $completedCount = count($completedIds);
        $progressPercent = $totalCount > 0 ? (int) round($completedCount / $totalCount * 100) : 0;

        $currentLessonId = $this->firstIncompleteLessonId($publishedLessons, $completedIds);
        $currentLesson = null;
        foreach ($publishedLessons as $lesson) {
            if ($lesson->getId() === $currentLessonId) {
                $currentLesson = (string) $lesson->getPublicId();
                break;
            }
        }

        return new ProgressReturnType(
            $progressPercent,
            $completedCount,
            $totalCount,
            $currentLesson,
            $enrollment->getStatus()->value,
        );
    }

    private function recomputeStatus(Enrollment $enrollment): void
    {
        $totalCount = count($this->lessonRepository->findPublishedByCourseOrdered($enrollment->getCourse()));
        $completedCount = count($this->lessonProgressRepository->findCompletedPublishedLessonIds($enrollment));

        if ($totalCount > 0 && $completedCount >= $totalCount) {
            $enrollment->setStatus(EnrollmentStatus::Completed);
            if (is_null($enrollment->getCompletedAt())) {
                $enrollment->setCompletedAt(new DateTime());
            }
        } else {
            $enrollment->setStatus(EnrollmentStatus::InProgress);
            $enrollment->setCompletedAt(null);
        }

        $this->enrollmentRepository->save($enrollment, true);
    }

    /**
     * @throws EnrollmentException
     */
    private function resolveAccessibleEnrollment(User $student, Ulid $coursePublicId): Enrollment
    {
        $course = $this->courseRepository->findOneByPublicId($coursePublicId);
        if (is_null($course)) {
            throw EnrollmentException::courseNotFound();
        }

        $enrollment = $this->enrollmentRepository->findOneByStudentAndCourse($student, $course);
        if (is_null($enrollment)) {
            throw EnrollmentException::enrollmentNotFound();
        }

        if (!$this->accessService->hasAccess($student, $course)) {
            throw EnrollmentException::purchaseRequired();
        }

        return $enrollment;
    }

    /**
     * @throws EnrollmentException
     */
    private function resolvePublishedLesson(Enrollment $enrollment, Ulid $lessonPublicId): Lesson
    {
        $lesson = $this->lessonRepository->findOneByPublicIdAndCourse($lessonPublicId, $enrollment->getCourse());

        if (is_null($lesson)) {
            throw EnrollmentException::lessonNotFound();
        }

        if ($lesson->getStatus() !== LessonStatus::Published) {
            throw EnrollmentException::lessonNotPublished();
        }

        return $lesson;
    }

    /**
     * @param Lesson[] $publishedLessons
     * @param array<int, mixed> $completedIds
     */
    private function firstIncompleteLessonId(array $publishedLessons, array $completedIds): ?int
    {
        foreach ($publishedLessons as $lesson) {
            if (!array_key_exists($lesson->getId(), $completedIds)) {
                return $lesson->getId();
            }
        }

        return null;
    }
}
