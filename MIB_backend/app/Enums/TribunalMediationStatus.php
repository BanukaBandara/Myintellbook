<?php

namespace App\Enums;

enum TribunalMediationStatus: string
{
    case Offered = 'offered';
    case AwaitingConsent = 'awaiting_consent';
    case Active = 'active';
    case Settled = 'settled';
    case Declined = 'declined';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
