<?php

declare(strict_types=1);

namespace App\Enum;

enum LessonType: string
{
    case Video = 'video';
    case Document = 'doc';
}
