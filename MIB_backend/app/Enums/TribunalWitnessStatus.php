<?php

namespace App\Enums;

enum TribunalWitnessStatus: string
{
    case Proposed = 'proposed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Testified = 'testified';
}
