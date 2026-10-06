<?php

namespace App\Enums;

enum TribunalDecisionOrderStatus: string
{
    case Pending = 'pending';
    case Recorded = 'recorded';
    case Active = 'active';
    case Completed = 'completed';
}
