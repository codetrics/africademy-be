<?php

declare(strict_types=1);

namespace App\Enum;

enum UserStatus: string
{
    case Active = 'active';
    case PendingReview = 'pending_review';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
}
