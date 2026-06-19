<?php

declare(strict_types=1);

namespace App\Enum;

enum EntitlementStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
}
