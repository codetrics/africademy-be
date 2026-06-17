<?php

declare(strict_types=1);

namespace App\Enum;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
