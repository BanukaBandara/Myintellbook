<?php

namespace App\Enums;

enum RestrictedFeature: string
{
    case TribunalParticipation = 'tribunal_participation';
    case CommunityPosting = 'community_posting';
    case DailyQuestionAccess = 'daily_question_access';
    case ExamAccess = 'exam_access';
    case ProfileEditing = 'profile_editing';
}
