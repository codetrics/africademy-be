<?php

declare(strict_types=1);

namespace App\Enum;

enum SubscriptionInterval: string
{
    case Monthly = 'monthly';
    case Annual = 'annual';

    public function modifier(): string
    {
        return match ($this) {
            self::Monthly => '+1 month',
            self::Annual => '+1 year',
        };
    }
}
