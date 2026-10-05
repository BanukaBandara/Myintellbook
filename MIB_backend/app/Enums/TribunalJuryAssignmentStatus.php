<?php

namespace App\Enums;

enum TribunalJuryAssignmentStatus: string
{
    case Invited = 'invited';
    case Accepted = 'accepted';
    case Recused = 'recused';
    case Replaced = 'replaced';
    case Completed = 'completed';
}
