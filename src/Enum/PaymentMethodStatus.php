<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentMethodStatus: string
{
    case Active = 'active';
    case Deleted = 'deleted';
}
