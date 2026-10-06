<?php

namespace App\Enums;

enum TribunalHearingStatus: string
{
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Recessed = 'recessed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
