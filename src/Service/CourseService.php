<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Course;
use App\Enum\CourseStatus;
use App\Repository\CourseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\String\Slugger\SluggerInterface;

class CourseService
{
    public function __construct(
        private readonly CourseRepository $courseRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * Assigns a unique slug derived from the title and persists the new course.
     */
    public function create(Course $course): Course
    {
        $course->setSlug($this->generateUniqueSlug($course->getTitle()));

        $this->entityManager->beginTransaction();
        try {
            $this->courseRepository->save($course, true);
            $this->entityManager->commit();
        } catch (Exception $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }

        return $course;
    }

    /**
     * Persists changes to an existing course. The slug is intentionally left
     * stable so public URLs do not break when the title is edited.
     */
    public function update(Course $course): Course
    {
        $this->courseRepository->save($course, true);

        return $course;
    }

    public function publish(Course $course): Course
    {
        $course->setStatus(CourseStatus::Published);
        $this->courseRepository->save($course, true);

        return $course;
    }

    public function unpublish(Course $course): Course
    {
        $course->setStatus(CourseStatus::Draft);
        $this->courseRepository->save($course, true);

        return $course;
    }

    public function delete(Course $course): void
    {
        $this->courseRepository->remove($course, true);
    }

    private function generateUniqueSlug(string $title): string
    {
        $base = $this->slugger->slug($title)->lower()->toString();

        if ($base === '') {
            $base = 'course';
        }

        $slug = $base;
        $suffix = 2;

        while ($this->courseRepository->slugExists($slug)) {
            $slug = sprintf('%s-%d', $base, $suffix);
            $suffix++;
        }

        return $slug;
    }
}
