<?php

namespace App\Enums;

enum InternalReportCategory: string
{
    case IdentityAndProfileFraud = 'Identity & Profile Fraud';
    case QualificationProfessionalFraud = 'Qualification / Professional Fraud';
    case HarassmentInappropriateBehaviour = 'Harassment & Inappropriate Behaviour';
    case ScamSecurityPrivacyViolation = 'Scam / Security / Privacy Violation';
    case AcademicScoreManipulation = 'Academic & Score Manipulation';
    case TribunalLegalProcessMisconduct = 'Tribunal / Legal Process Misconduct';
    case ContentCommunityAbuse = 'Content & Community Abuse';
    case OtherPlatformMisconduct = 'Other Platform Misconduct';
}
