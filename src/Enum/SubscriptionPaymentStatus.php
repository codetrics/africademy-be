<?php

declare(strict_types=1);

namespace App\Enum;

enum SubscriptionPaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
}
