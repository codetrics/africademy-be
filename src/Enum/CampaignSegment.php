<?php

declare(strict_types=1);

namespace App\Enum;

enum CampaignSegment: string
{
    case AllStudents = 'all_students';
    case ActiveSubscribers = 'active_subscribers';
    case NewsletterSubscribers = 'newsletter_subscribers';
}
