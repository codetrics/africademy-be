<?php

declare(strict_types=1);

namespace App\Enum;

enum EntitlementSource: string
{
    case Free = 'free';
    case CoursePurchase = 'course_purchase';
    case BundlePurchase = 'bundle_purchase';
    case Subscription = 'subscription';
}
