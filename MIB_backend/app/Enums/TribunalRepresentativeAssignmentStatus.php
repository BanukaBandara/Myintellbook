<?php

namespace App\Enums;

enum TribunalRepresentativeAssignmentStatus: string
{
    case Active = 'active';
    case Ended = 'ended';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active Representation',
            self::Ended => 'Representation Ended',
            self::Revoked => 'Revoked',
        };
    }
}
