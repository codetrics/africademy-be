<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The outcome recorded for a signature-valid PayFast ITN. Invalid-signature ITNs
 * are logged to the payfast channel but never stored.
 */
enum PayfastWebhookOutcome: string
{
    case OrderCompleted = 'order_completed';
    case Duplicate = 'duplicate';
    case AmountMismatch = 'amount_mismatch';
    case Unmatched = 'unmatched';
    case NotComplete = 'not_complete';
    case Tokenization = 'tokenization';
}
