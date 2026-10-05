<?php

namespace App\Enums;

enum TribunalEvidenceStatus: string
{
    case Submitted = 'submitted';
    case Challenged = 'challenged';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case VerificationRequired = 'verification_required';
}
