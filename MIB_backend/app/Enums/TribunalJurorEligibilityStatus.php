<?php

namespace App\Enums;

enum TribunalJurorEligibilityStatus: string
{
    case Pending = 'pending';
    case Eligible = 'eligible';
    case Suspended = 'suspended';
    case Inactive = 'inactive';
}
