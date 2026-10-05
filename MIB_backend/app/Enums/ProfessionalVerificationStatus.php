<?php

namespace App\Enums;

enum ProfessionalVerificationStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Rejected, self::Expired], true);
    }
}
