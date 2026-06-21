<?php

declare(strict_types=1);

namespace App\Enum;

enum AccountType: string
{
    case Student = 'student';
    case Teacher = 'teacher';
}
