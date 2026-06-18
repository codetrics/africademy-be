<?php

declare(strict_types=1);

namespace App\Enum;

enum EnrollmentStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
