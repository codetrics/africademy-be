<?php

declare(strict_types=1);

namespace App\Enum;

enum NewsletterStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Unsubscribed = 'unsubscribed';
}
