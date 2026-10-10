<?php

namespace App\Enums;

enum TribunalJuryPanelStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
}
