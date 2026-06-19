<?php

declare(strict_types=1);

namespace App\Enum;

enum CourseStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
