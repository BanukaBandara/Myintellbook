<?php

namespace App\Enums;

enum InternalPenaltyType: string
{
    case Warning = 'Warning';
    case FormalWarning = 'Formal Warning';
    case ProfileCorrectionRequired = 'Profile Correction Required';
}
