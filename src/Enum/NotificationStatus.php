<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
}
