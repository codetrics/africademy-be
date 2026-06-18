<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Per-student, computed lesson state for the learn view (not persisted).
 */
enum LessonState: string
{
    case Done = 'done';
    case Current = 'current';
    case Upcoming = 'upcoming';
}
