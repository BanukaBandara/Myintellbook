<?php

namespace App\Enums;

enum TribunalDeliberationNoteType: string
{
    case General = 'general';
    case EvidenceAnalysis = 'evidence_analysis';
    case WitnessAnalysis = 'witness_analysis';
    case Credibility = 'credibility';
    case IssueAnalysis = 'issue_analysis';
    case RemedyConsideration = 'remedy_consideration';
}
