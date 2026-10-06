<?php

namespace App\Enums;

enum TribunalJuryPanelAssignmentMethod: string
{
    case Automatic = 'automatic';
    case ManualReassignment = 'manual_reassignment';
}
