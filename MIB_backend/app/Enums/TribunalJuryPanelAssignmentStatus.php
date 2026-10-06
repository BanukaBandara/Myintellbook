<?php

namespace App\Enums;

enum TribunalJuryPanelAssignmentStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Released = 'released';
}
