<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Repository\LessonRepository;

class LessonService
{
    public function __construct(
        private readonly LessonRepository $lessonRepository,
    ) {
    }

    /**
     * Appends a lesson to a course at the next available position.
     */
    public function addToCourse(Course $course, Lesson $lesson): Lesson
    {
        $lesson->setCourse($course);
        $lesson->setPosition($this->lessonRepository->getNextPosition($course));
        $course->addLesson($lesson);

        $this->lessonRepository->save($lesson, true);

        return $lesson;
    }

    public function update(Lesson $lesson): Lesson
    {
        $this->lessonRepository->save($lesson, true);

        return $lesson;
    }

    public function delete(Lesson $lesson): void
    {
        $this->lessonRepository->remove($lesson, true);
    }
}
