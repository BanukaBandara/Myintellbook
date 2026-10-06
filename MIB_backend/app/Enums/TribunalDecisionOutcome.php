<?php

namespace App\Enums;

enum TribunalDecisionOutcome: string
{
    case ComplaintUpheld = 'complaint_upheld';
    case ComplaintPartiallyUpheld = 'complaint_partially_upheld';
    case ComplaintNotUpheld = 'complaint_not_upheld';
    case Dismissed = 'dismissed';
}
