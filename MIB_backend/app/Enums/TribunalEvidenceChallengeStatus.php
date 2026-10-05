<?php

namespace App\Enums;

enum TribunalEvidenceChallengeStatus: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
