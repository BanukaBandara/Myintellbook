<?php

namespace App\Enums;

enum TribunalHearingEntryType: string
{
    case OpeningStatement = 'opening_statement';
    case ResponseStatement = 'response_statement';
    case JuryQuestion = 'jury_question';
    case PartyAnswer = 'party_answer';
    case WitnessTestimony = 'witness_testimony';
    case WitnessQuestion = 'witness_question';
    case WitnessAnswer = 'witness_answer';
    case EvidenceReference = 'evidence_reference';
    case ProceduralDirection = 'procedural_direction';
    case ClosingStatement = 'closing_statement';
    case SystemEvent = 'system_event';
}
