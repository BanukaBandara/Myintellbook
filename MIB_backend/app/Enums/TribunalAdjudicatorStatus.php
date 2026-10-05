<?php

namespace App\Enums;

enum TribunalAdjudicatorStatus: string
{
    case Pending = 'pending';
    case Eligible = 'eligible';
    case Suspended = 'suspended';
    case Inactive = 'inactive';
}
