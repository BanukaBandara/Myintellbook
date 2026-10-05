<?php

namespace App\Services\Tribunal;

use App\Enums\ProfessionalType;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\TribunalAdjudicatorStatus;
use App\Enums\TribunalCaseStatus;
use App\Enums\TribunalJurorEligibilityStatus;
use App\Enums\TribunalJuryAssignmentStatus;
use App\Enums\TribunalJuryRole;
use App\Enums\TribunalQualificationStatus;
use App\Models\ProfessionalVerification;
use App\Models\TribunalAdjudicatorProfile;
use App\Models\TribunalCase;
use App\Models\TribunalJurorConflict;
use App\Models\TribunalJurorProfile;
use App\Models\TribunalJuryAssignment;
use App\Notifications\Tribunal\TribunalJuryAcceptedNotification;
use App\Notifications\Tribunal\TribunalJuryAssignedNotification;
use App\Notifications\Tribunal\TribunalJuryRecusedNotification;
use Illuminate\Support\Facades\DB;

class JurySelectionService
{
    /**
     * Attempt to select and assign a random eligible juror to the case.
     */
    public function assignJurorToCase(TribunalCase $tribunalCase): ?TribunalJuryAssignment
    {
        return DB::transaction(function () use ($tribunalCase) {
            // IDs of case parties to exclude
            $excludedUserIds = $tribunalCase->parties()->pluck('user_id')->all();
            if ($tribunalCase->created_by) {
                $excludedUserIds[] = $tribunalCase->created_by;
            }

            // Already assigned jurors for this case (including recused ones)
            $alreadyAssignedJurorIds = $tribunalCase->juryAssignments()
                ->pluck('juror_id')
                ->all();

            $excludedUserIds = array_unique(array_merge($excludedUserIds, $alreadyAssignedJurorIds));

            // Eligible and available adjudicator profiles:
            // Must have verified professional status, allowed profession type,
            // valid qualification, eligible status, available, not suspended, and not expired.
            $eligibleProfile = TribunalAdjudicatorProfile::query()
                ->where('status', TribunalAdjudicatorStatus::Eligible)
                ->where('available', true)
                ->whereIn('qualification_status', [TribunalQualificationStatus::Passed, TribunalQualificationStatus::Exempted])
                ->whereNull('suspended_at')
                ->whereNotIn('user_id', $excludedUserIds)
                ->whereHas('professionalVerification', function ($query) {
                    $query->where('verification_status', ProfessionalVerificationStatus::Verified)
                        ->whereIn('profession_type', ProfessionalType::adjudicatorEligibleTypes())
                        ->where(function ($dateQ) {
                            $dateQ->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                        });
                })
                ->inRandomOrder()
                ->first();

            if (!$eligibleProfile) {
                // No eligible verified adjudicator available at this time
                // Leave case in jury_selection without failing
                $tribunalCase->update([
                    'status' => TribunalCaseStatus::JurySelection,
                ]);

                return null;
            }

            $assignment = TribunalJuryAssignment::create([
                'tribunal_case_id' => $tribunalCase->id,
                'juror_id' => $eligibleProfile->user_id,
                'role' => TribunalJuryRole::Juror,
                'status' => TribunalJuryAssignmentStatus::Invited,
                'assigned_at' => now(),
            ]);

            // Sync legacy juror profile if present
            TribunalJurorProfile::where('user_id', $eligibleProfile->user_id)->increment('cases_active');

            $tribunalCase->update([
                'status' => TribunalCaseStatus::JurySelection,
            ]);

            // Log event
            TribunalCaseEventService::log($tribunalCase, 'jury_assigned', $eligibleProfile->user_id, [
                'assignment_id' => $assignment->id,
                'adjudicator_id' => $eligibleProfile->user_id,
                'role' => TribunalJuryRole::Juror->value,
            ]);

            // Notify adjudicator
            $eligibleProfile->user?->notify(
                new TribunalJuryAssignedNotification($tribunalCase, $assignment)
            );

            return $assignment->load(['juror.profile']);
        });
    }

    /**
     * Juror declares conflict of interest.
     */
    public function declareConflict(
        TribunalCase $tribunalCase,
        int $jurorId,
        bool $hasConflict,
        ?string $conflictReason = null
    ): TribunalJuryAssignment {
        $assignment = $tribunalCase->juryAssignments()
            ->where('juror_id', $jurorId)
            ->where('status', TribunalJuryAssignmentStatus::Invited)
            ->first();

        abort_unless(
            $assignment,
            403,
            'You do not have a pending jury invitation for this case.'
        );

        if ($hasConflict) {
            abort_if(
                empty(trim((string) $conflictReason)),
                422,
                'A valid reason is required when declaring a conflict of interest.'
            );
        }

        return DB::transaction(function () use ($tribunalCase, $assignment, $jurorId, $hasConflict, $conflictReason) {
            TribunalJurorConflict::create([
                'tribunal_jury_assignment_id' => $assignment->id,
                'juror_id' => $jurorId,
                'has_conflict' => $hasConflict,
                'conflict_reason' => $hasConflict ? $conflictReason : null,
                'declared_at' => now(),
            ]);

            if ($hasConflict) {
                // Juror is recused
                $assignment->update([
                    'status' => TribunalJuryAssignmentStatus::Recused,
                    'responded_at' => now(),
                    'recusal_reason' => $conflictReason,
                ]);

                // Decrement active cases on profile
                TribunalJurorProfile::where('user_id', $jurorId)
                    ->where('cases_active', '>', 0)
                    ->decrement('cases_active');

                TribunalCaseEventService::log($tribunalCase, 'jury_recused', $jurorId, [
                    'assignment_id' => $assignment->id,
                    'juror_id' => $jurorId,
                ]);

                // Notify case parties that selection is ongoing
                foreach ($tribunalCase->parties as $party) {
                    $party->user?->notify(new TribunalJuryRecusedNotification($tribunalCase));
                }

                // Automatically attempt to select a replacement juror
                $this->assignJurorToCase($tribunalCase);

                return $assignment;
            }

            // No conflict: juror accepts assignment
            $assignment->update([
                'status' => TribunalJuryAssignmentStatus::Accepted,
                'responded_at' => now(),
            ]);

            // Case transitions to evidence_collection
            $tribunalCase->update([
                'status' => TribunalCaseStatus::EvidenceCollection,
            ]);

            TribunalCaseEventService::log($tribunalCase, 'jury_accepted', $jurorId, [
                'assignment_id' => $assignment->id,
                'juror_id' => $jurorId,
            ]);

            // Notify case parties that juror has accepted
            foreach ($tribunalCase->parties as $party) {
                $party->user?->notify(new TribunalJuryAcceptedNotification($tribunalCase, $assignment));
            }

            return $assignment->load(['juror.profile']);
        });
    }

    /**
     * Juror explicitly accepts (wrapper confirming no conflict).
     */
    public function acceptAssignment(TribunalCase $tribunalCase, int $jurorId): TribunalJuryAssignment
    {
        return $this->declareConflict($tribunalCase, $jurorId, false);
    }

    /**
     * Juror explicitly recuses with reason.
     */
    public function recuseAssignment(
        TribunalCase $tribunalCase,
        int $jurorId,
        string $recusalReason
    ): TribunalJuryAssignment {
        return $this->declareConflict($tribunalCase, $jurorId, true, $recusalReason);
    }
}
