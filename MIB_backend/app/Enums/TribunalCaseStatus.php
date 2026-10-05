<?php

namespace App\Enums;

enum TribunalCaseStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case AwaitingRespondent = 'awaiting_respondent';
    case ResponseReceived = 'response_received';
    case Mediation = 'mediation';
    case JurySelection = 'jury_selection';
    case EvidenceCollection = 'evidence_collection';
    case Hearing = 'hearing';
    case Deliberation = 'deliberation';
    case Decided = 'decided';
    case AppealWindow = 'appeal_window';
    case Appealed = 'appealed';
    case Closed = 'closed';
    case Settled = 'settled';
    case Withdrawn = 'withdrawn';
    case Dismissed = 'dismissed';
    case Escalated = 'escalated';
}
