<?php

declare(strict_types=1);

namespace App\Enum;

enum CommunityPostStatus: string
{
    case Published = 'published';
    case Hidden = 'hidden';
}
