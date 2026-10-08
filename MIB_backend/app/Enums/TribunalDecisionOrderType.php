<?php

namespace App\Enums;

enum TribunalDecisionOrderType: string
{
    case NoAction = 'no_action';
    case Warning = 'warning';
    case CorrectiveAction = 'corrective_action';
    case ContentAction = 'content_action';
    case AccountAction = 'account_action';
    case CompensationRecommendation = 'compensation_recommendation';
    case ComplianceRequirement = 'compliance_requirement';
    case Other = 'other';
}
