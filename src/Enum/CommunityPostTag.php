<?php

declare(strict_types=1);

namespace App\Enum;

enum CommunityPostTag: string
{
    case Resource = 'resource';
    case Question = 'question';
    case Showcase = 'showcase';
    case Mentorship = 'mentorship';
}
