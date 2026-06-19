<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Course;
use App\Entity\Review;
use App\Entity\User;
use App\Exceptions\ReviewException;
use App\Repository\CourseRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Ulid;

class ReviewService
{
    public function __construct(
        private readonly ReviewRepository $reviewRepository,
        private readonly EnrollmentRepository $enrollmentRepository,
        private readonly CourseRepository $courseRepository,
    ) {
    }

    /**
     * @throws ReviewException
     */
    public function create(User $student, Ulid $coursePublicId, int $rating, ?string $body): Review
    {
        $course = $this->resolveCourse($coursePublicId);
        $this->assertRating($rating);

        if (is_null($this->enrollmentRepository->findOneByStudentAndCourse($student, $course))) {
            throw ReviewException::notEnrolled();
        }

        if (!is_null($this->reviewRepository->findOneByCourseAndStudent($course, $student))) {
            throw ReviewException::alreadyReviewed();
        }

        $review = new Review();
        $review->setStudent($student);
        $review->setCourse($course);
        $review->setRating($rating);
        $review->setBody($body);
        $this->reviewRepository->save($review, true);

        $this->recalculate($course);

        return $review;
    }

    /**
     * @throws ReviewException
     */
    public function update(Review $review, int $rating, ?string $body): Review
    {
        $this->assertRating($rating);
        $review->setRating($rating);
        $review->setBody($body);
        $this->reviewRepository->save($review, true);

        $this->recalculate($review->getCourse());

        return $review;
    }

    public function delete(Review $review): void
    {
        $course = $review->getCourse();
        $this->reviewRepository->remove($review, true);
        $this->recalculate($course);
    }

    /**
     * @throws ReviewException
     */
    public function getStudentReview(User $student, Ulid $publicId): Review
    {
        $review = $this->reviewRepository->findOneByPublicId($publicId);

        if (is_null($review) || $review->getStudent()->getId() !== $student->getId()) {
            throw ReviewException::notFound();
        }

        return $review;
    }

    /**
     * @throws ReviewException
     */
    public function courseReviewsQueryBuilder(Ulid $coursePublicId): QueryBuilder
    {
        return $this->reviewRepository->createCourseReviewsQueryBuilder($this->resolveCourse($coursePublicId));
    }

    /**
     * @throws ReviewException
     */
    private function resolveCourse(Ulid $coursePublicId): Course
    {
        $course = $this->courseRepository->findOneByPublicId($coursePublicId);

        if (is_null($course)) {
            throw ReviewException::courseNotFound();
        }

        return $course;
    }

    /**
     * @throws ReviewException
     */
    private function assertRating(int $rating): void
    {
        if ($rating < 1 || $rating > 5) {
            throw ReviewException::invalidRating();
        }
    }

    private function recalculate(Course $course): void
    {
        $stats = $this->reviewRepository->ratingStats($course);
        $course->setRatingAverage($stats['average']);
        $course->setRatingCount($stats['count']);
        $this->courseRepository->save($course, true);
    }
}
