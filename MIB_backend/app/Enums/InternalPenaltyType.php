<?php

namespace App\Enums;

enum InternalPenaltyType: string
{
    case Warning = 'Warning';
    case FormalWarning = 'Formal Warning';
    case ProfileCorrectionRequired = 'Profile Correction Required';
    case TemporarySuspension = 'Temporary Suspension';
    case PermanentSuspension = 'Permanent Suspension';
    case FeatureRestriction = 'Feature Restriction';
    case VerificationRevoked = 'Verification Revoked';
    case ProfessionalEligibilitySuspension = 'Professional Eligibility Suspension';
    case JuryPanelDeactivation = 'Jury Panel Deactivation';
    case HipScorePenalty = 'HIP / Score Penalty';
}
