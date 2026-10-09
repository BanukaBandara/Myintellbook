<?php

namespace App\Http\Controllers\Tribunal;

use App\Enums\TribunalJuryAssignmentStatus;
use App\Enums\TribunalPartyRole;
use App\Http\Controllers\Controller;
use App\Models\ProfessionalVerification;
use App\Models\TribunalAdjudicatorProfile;
use App\Models\TribunalCaseParty;
use App\Models\TribunalJuryAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TribunalMeController extends Controller
{
    /**
     * Return the authenticated user's Tribunal capabilities and roles.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check complainant and respondent status
        $hasComplainantCases = TribunalCaseParty::where('user_id', $user->id)
            ->where('role', TribunalPartyRole::Complainant)
            ->exists();

        $hasRespondentCases = TribunalCaseParty::where('user_id', $user->id)
            ->where('role', TribunalPartyRole::Respondent)
            ->exists();

        // Check professional verification
        $verification = ProfessionalVerification::where('user_id', $user->id)->first();
        $profVerificationData = null;

        if ($verification) {
            $profVerificationData = [
                'id' => $verification->id,
                'profession_type' => $verification->profession_type instanceof \App\Enums\ProfessionalType
                    ? $verification->profession_type->value
                    : (string) $verification->profession_type,
                'profession_label' => $verification->profession_type instanceof \App\Enums\ProfessionalType
                    ? $verification->profession_type->label()
                    : (string) $verification->profession_type,
                'status' => $verification->verification_status instanceof \App\Enums\ProfessionalVerificationStatus
                    ? $verification->verification_status->value
                    : (string) $verification->verification_status,
                'is_verified' => $verification->isVerified(),
                'badge_title' => $verification->isVerified()
                    ? 'Verified ' . ($verification->profession_type instanceof \App\Enums\ProfessionalType ? $verification->profession_type->label() : 'Legal Professional')
                    : null,
            ];
        }

        // Check adjudicator profile and assignments
        $adjudicatorProfile = TribunalAdjudicatorProfile::where('user_id', $user->id)->first();

        $pendingAssignmentsCount = TribunalJuryAssignment::where('juror_id', $user->id)
            ->where('status', TribunalJuryAssignmentStatus::Invited)
            ->count();

        $activeAssignmentsCount = TribunalJuryAssignment::where('juror_id', $user->id)
            ->where('status', TribunalJuryAssignmentStatus::Accepted)
            ->count();

        $adjudicatorData = [
            'eligible' => $adjudicatorProfile ? $adjudicatorProfile->isEligibleAdjudicator() : false,
            'available' => $adjudicatorProfile ? (bool) $adjudicatorProfile->available : false,
            'status' => $adjudicatorProfile ? ($adjudicatorProfile->status?->value ?? $adjudicatorProfile->status) : null,
            'qualification_status' => $adjudicatorProfile ? ($adjudicatorProfile->qualification_status?->value ?? $adjudicatorProfile->qualification_status) : null,
            'pending_assignments' => $pendingAssignmentsCount,
            'active_assignments' => $activeAssignmentsCount,
        ];

        $isRepEligible = $user->canActAsLegalRepresentative()
            && !app(\App\Services\InternalTribunal\AccountProfessionalDisciplineService::class)->hasActiveEligibilitySuspension($user);
        $pendingRequestsCount = 0;
        $activeRepresentedCasesCount = 0;

        if ($isRepEligible) {
            $pendingRequestsCount = \App\Models\TribunalRepresentationRequest::where('representative_user_id', $user->id)
                ->where('status', \App\Enums\TribunalRepresentationRequestStatus::Pending)
                ->count();

            $activeRepresentedCasesCount = \App\Models\TribunalRepresentativeAssignment::where('representative_user_id', $user->id)
                ->where('status', \App\Enums\TribunalRepresentativeAssignmentStatus::Active)
                ->count();
        }

        $representativeData = [
            'eligible' => $isRepEligible,
            'pending_requests' => $pendingRequestsCount,
            'active_cases' => $activeRepresentedCasesCount,
        ];

        return response()->json([
            'can_submit_cases' => true,
            'has_cases_as_complainant' => $hasComplainantCases,
            'has_cases_as_respondent' => $hasRespondentCases,
            'is_admin_reviewer' => $user->isAdmin(),
            'is_jury_panel' => $user->isJuryPanelAccount(),
            'can_act_as_representative' => $isRepEligible,
            'professional_verification' => $profVerificationData,
            'adjudicator' => $adjudicatorData,
            'representative' => $representativeData,
        ]);
    }
}
