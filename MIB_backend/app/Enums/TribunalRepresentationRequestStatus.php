<?php

namespace App\Enums;

enum TribunalRepresentationRequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Review',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
            self::Cancelled => 'Cancelled',
            self::Ended => 'Ended',
        };
    }
}
