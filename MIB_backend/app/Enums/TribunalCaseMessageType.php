<?php

namespace App\Enums;

enum TribunalCaseMessageType: string
{
    case Message = 'message';
    case ProceduralNotice = 'procedural_notice';
    case AdjudicatorQuestion = 'adjudicator_question';
    case QuestionResponse = 'question_response';
    case MediationNotice = 'mediation_notice';
    case SystemNotice = 'system_notice';
}
