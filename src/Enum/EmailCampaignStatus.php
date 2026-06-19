<?php

declare(strict_types=1);

namespace App\Enum;

enum EmailCampaignStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
}
