<?php

namespace App\Services\Tribunal;

use App\Enums\TribunalCaseStatus;
use App\Enums\TribunalHearingEntryType;
use App\Enums\TribunalHearingLocationType;
use App\Enums\TribunalHearingStatus;
use App\Enums\TribunalHearingType;
use App\Enums\TribunalWitnessStatus;
use App\Models\TribunalCase;
use App\Models\TribunalEvidence;
use App\Models\TribunalHearing;
use App\Models\TribunalHearingEntry;
use App\Models\TribunalHearingParticipant;
use App\Models\TribunalWitness;
use App\Models\User;
use App\Notifications\Tribunal\TribunalHearingCompletedNotification;
use App\Notifications\Tribunal\TribunalHearingQuestionNotification;
use App\Notifications\Tribunal\TribunalHearingRecessedNotification;
use App\Notifications\Tribunal\TribunalHearingResumedNotification;
use App\Notifications\Tribunal\TribunalHearingScheduledNotification;
use App\Notifications\Tribunal\TribunalHearingStartedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TribunalHearingService
{
    /**
     * Schedule a formal hearing for a tribunal case.
     * Only the assigned active Jury Panel can schedule.
     */
    public function scheduleHearing(TribunalCase $case, int $userId, array $data): TribunalHearing
    {
        // 1. Authorize: Only assigned active Jury Panel
        if (!$case->isAssignedJuryPanelUser($userId)) {
            abort(403, 'Unauthorized. Only the assigned active Jury Panel may schedule a hearing for this case.');
        }

        // 2. Eligibility: Case must not be resolved or inactive
        if (in_array($case->status, [
            TribunalCaseStatus::Decided,
            TribunalCaseStatus::Closed,
            TribunalCaseStatus::Settled,
            TribunalCaseStatus::Withdrawn,
            TribunalCaseStatus::Dismissed,
        ])) {
            abort(422, 'Cannot schedule a hearing for a concluded case.');
        }

        // 3. Ensure only one active/scheduled formal hearing exists at a time
        $hasActiveOrScheduled = $case->hearings()
            ->whereIn('status', [
                TribunalHearingStatus::Scheduled,
                TribunalHearingStatus::Active,
                TribunalHearingStatus::Recessed,
            ])
            ->exists();

        if ($hasActiveOrScheduled) {
            abort(422, 'Case already has an active or scheduled formal hearing.');
        }

        // 4. Resolve panel assignment
        $assignment = $case->currentJuryPanelAssignment;
        $panel = $assignment ? $assignment->juryPanel : null;
        if (!$panel) {
            abort(422, 'No active Jury Panel assigned to this case.');
        }

        return DB::transaction(function () use ($case, $userId, $panel, $data) {
            // Generate sequential hearing number e.g. HRG-0001
            $nextSeq = (TribunalHearing::max('id') ?? 0) + 1;
            $hearingNumber = 'HRG-' . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);

            $hearingType = isset($data['hearing_type'])
                ? (is_string($data['hearing_type']) ? TribunalHearingType::from($data['hearing_type']) : $data['hearing_type'])
                : TribunalHearingType::Formal;

            $locationType = isset($data['location_type'])
                ? (is_string($data['location_type']) ? TribunalHearingLocationType::from($data['location_type']) : $data['location_type'])
                : TribunalHearingLocationType::Online;

            $hearing = TribunalHearing::create([
                'tribunal_case_id' => $case->id,
                'tribunal_jury_panel_id' => $panel->id,
                'hearing_number' => $hearingNumber,
                'hearing_type' => $hearingType,
                'status' => TribunalHearingStatus::Scheduled,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'location_type' => $locationType,
                'meeting_link' => $data['meeting_link'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            // Auto-populate participants
            $this->populateParticipants($hearing, $panel, $userId);

            // Audit event
            TribunalCaseEventService::log($case, 'hearing_scheduled', $userId, [
                'hearing_id' => $hearing->id,
                'hearing_number' => $hearing->hearing_number,
                'hearing_type' => $hearing->hearing_type->value,
                'scheduled_at' => $hearing->scheduled_at?->toIso8601String(),
            ]);

            // Notify parties and lawyers (excluding scheduling jury user)
            $recipients = $this->getHearingNotificationRecipients($case, $userId);
            if (!empty($recipients)) {
                Notification::send($recipients, new TribunalHearingScheduledNotification($hearing));
            }

            return $hearing->load(['participants.user.profile', 'juryPanel']);
        });
    }

    /**
     * Start a scheduled hearing.
     * Transitions hearing to 'active' and case status to 'hearing'.
     */
    public function startHearing(TribunalHearing $hearing, int $userId): TribunalHearing
    {
        $case = $hearing->case;
        if (!$case->isAssignedJuryPanelUser($userId)) {
            abort(403, 'Unauthorized. Only the assigned Jury Panel can start the hearing.');
        }

        if ($hearing->status !== TribunalHearingStatus::Scheduled) {
            abort(422, 'Hearing cannot be started from its current status.');
        }

        return DB::transaction(function () use ($hearing, $case, $userId) {
            $hearing->update([
                'status' => TribunalHearingStatus::Active,
                'started_at' => now(),
            ]);

            $case->update([
                'status' => TribunalCaseStatus::Hearing,
            ]);

            // Add system opening entry
            $this->createEntry($hearing, null, 'system', null, TribunalHearingEntryType::SystemEvent, "Formal hearing {$hearing->hearing_number} commenced by Jury Panel.");

            // Audit
            TribunalCaseEventService::log($case, 'hearing_started', $userId, [
                'hearing_id' => $hearing->id,
                'hearing_number' => $hearing->hearing_number,
            ]);

            // Notify
            $recipients = $this->getHearingNotificationRecipients($case, $userId);
            if (!empty($recipients)) {
                Notification::send($recipients, new TribunalHearingStartedNotification($hearing));
            }

            return $hearing;
        });
    }

    /**
     * Recess an active hearing temporarily.
     */
    public function recessHearing(TribunalHearing $hearing, int $userId): TribunalHearing
    {
        $case = $hearing->case;
        if (!$case->isAssignedJuryPanelUser($userId)) {
            abort(403, 'Unauthorized. Only the assigned Jury Panel can recess the hearing.');
        }

        if ($hearing->status !== TribunalHearingStatus::Active) {
            abort(422, 'Only an active hearing can be recessed.');
        }

        return DB::transaction(function () use ($hearing, $case, $userId) {
            $hearing->update([
                'status' => TribunalHearingStatus::Recessed,
            ]);

            $this->createEntry($hearing, $userId, 'jury_panel', 'neutral', TribunalHearingEntryType::ProceduralDirection, "The Jury Panel has ordered a temporary recess in hearing {$hearing->hearing_number}.");

            TribunalCaseEventService::log($case, 'hearing_recessed', $userId, [
                'hearing_id' => $hearing->id,
                'hearing_number' => $hearing->hearing_number,
            ]);

            $recipients = $this->getHearingNotificationRecipients($case, $userId);
            if (!empty($recipients)) {
                Notification::send($recipients, new TribunalHearingRecessedNotification($hearing));
            }

            return $hearing;
        });
    }

    /**
     * Resume a recessed hearing.
     */
    public function resumeHearing(TribunalHearing $hearing, int $userId): TribunalHearing
    {
        $case = $hearing->case;
        if (!$case->isAssignedJuryPanelUser($userId)) {
            abort(403, 'Unauthorized. Only the assigned Jury Panel can resume the hearing.');
        }

        if ($hearing->status !== TribunalHearingStatus::Recessed) {
            abort(422, 'Only a recessed hearing can be resumed.');
        }

        return DB::transaction(function () use ($hearing, $case, $userId) {
            $hearing->update([
                'status' => TribunalHearingStatus::Active,
            ]);

            $this->createEntry($hearing, $userId, 'jury_panel', 'neutral', TribunalHearingEntryType::ProceduralDirection, "Hearing {$hearing->hearing_number} has resumed.");

            TribunalCaseEventService::log($case, 'hearing_resumed', $userId, [
                'hearing_id' => $hearing->id,
                'hearing_number' => $hearing->hearing_number,
            ]);

            $recipients = $this->getHearingNotificationRecipients($case, $userId);
            if (!empty($recipients)) {
                Notification::send($recipients, new TribunalHearingResumedNotification($hearing));
            }

            return $hearing;
        });
    }

    /**
     * Close hearing formally and move case status to 'deliberation'.
     * Does NOT create decision or mark decided!
     */
    public function closeHearing(TribunalHearing $hearing, int $userId): TribunalHearing
    {
        $case = $hearing->case;
        if (!$case->isAssignedJuryPanelUser($userId)) {
            abort(403, 'Unauthorized. Only the assigned Jury Panel can close the hearing.');
        }

        if (!in_array($hearing->status, [TribunalHearingStatus::Active, TribunalHearingStatus::Recessed])) {
            abort(422, 'Hearing cannot be closed from its current status.');
        }

        return DB::transaction(function () use ($hearing, $case, $userId) {
            $hearing->update([
                'status' => TribunalHearingStatus::Completed,
                'ended_at' => now(),
            ]);

            // Transition case status to deliberation (deliberation itself is Step 6!)
            $case->update([
                'status' => TribunalCaseStatus::Deliberation,
            ]);

            $this->createEntry($hearing, $userId, 'jury_panel', 'neutral', TribunalHearingEntryType::SystemEvent, "Formal hearing {$hearing->hearing_number} closed. Case transitioned to deliberation.");

            TribunalCaseEventService::log($case, 'hearing_completed', $userId, [
                'hearing_id' => $hearing->id,
                'hearing_number' => $hearing->hearing_number,
            ]);

            $recipients = $this->getHearingNotificationRecipients($case, $userId);
            if (!empty($recipients)) {
                Notification::send($recipients, new TribunalHearingCompletedNotification($hearing));
            }

            return $hearing;
        });
    }

    /**
     * Add a structured entry to the immutable hearing transcript.
     */
    public function addEntry(TribunalHearing $hearing, int $userId, array $data): TribunalHearingEntry
    {
        if ($hearing->status !== TribunalHearingStatus::Active) {
            abort(422, 'Hearing entries can only be submitted during an active hearing session.');
        }

        $case = $hearing->case;
        $userRole = $case->getUserCaseRole($userId);
        if (!$userRole) {
            abort(403, 'Unauthorized. You are not an active participant, counsel, or jury panel for this case.');
        }

        $rawEntryType = $data['entry_type'] ?? null;
        $entryType = is_string($rawEntryType) ? TribunalHearingEntryType::from($rawEntryType) : $rawEntryType;
        if (!$entryType) {
            abort(422, 'Valid entry_type is required.');
        }

        $userSide = $case->getUserCaseSide($userId);

        // Role-based restrictions: Jury Panel cannot submit statements as party
        if ($userRole === 'jury_panel') {
            if (in_array($entryType, [
                TribunalHearingEntryType::OpeningStatement,
                TribunalHearingEntryType::ClosingStatement,
                TribunalHearingEntryType::PartyAnswer,
                TribunalHearingEntryType::ResponseStatement,
            ])) {
                abort(403, 'Jury Panel cannot submit statements or answers on behalf of parties.');
            }
        } else {
            // Normal parties/lawyers cannot submit jury questions or procedural directions
            if (in_array($entryType, [
                TribunalHearingEntryType::JuryQuestion,
                TribunalHearingEntryType::ProceduralDirection,
                TribunalHearingEntryType::SystemEvent,
            ])) {
                abort(403, 'Parties and counsel cannot issue procedural directions or jury questions.');
            }
        }

        // Validate evidence reference if present
        $evidenceId = $data['related_evidence_id'] ?? null;
        if ($evidenceId) {
            $evidence = TribunalEvidence::find($evidenceId);
            if (!$evidence || $evidence->tribunal_case_id !== $case->id) {
                abort(422, 'Referenced evidence does not belong to this tribunal case.');
            }
        }

        // Validate witness reference if present
        $witnessId = $data['related_witness_id'] ?? null;
        if ($witnessId) {
            $witness = TribunalWitness::find($witnessId);
            if (!$witness || $witness->tribunal_case_id !== $case->id) {
                abort(422, 'Referenced witness does not belong to this tribunal case.');
            }
            if ($entryType === TribunalHearingEntryType::WitnessTestimony && !$witness->isApproved() && !$witness->isTestified()) {
                abort(422, 'Witness has not been approved by the Jury Panel to testify.');
            }
        }

        $parentEntryId = $data['parent_entry_id'] ?? null;
        if ($parentEntryId) {
            $parent = TribunalHearingEntry::where('tribunal_hearing_id', $hearing->id)->find($parentEntryId);
            if (!$parent) {
                abort(422, 'Referenced parent question/entry does not exist in this hearing.');
            }
        }

        $targetSide = $data['target_side'] ?? null;

        $entry = $this->createEntry(
            $hearing,
            $userId,
            $userRole,
            $userSide,
            $entryType,
            $data['body'],
            $witnessId,
            $evidenceId,
            $targetSide,
            $parentEntryId
        );

        // Audit specific entry types
        $auditEvent = match ($entryType) {
            TribunalHearingEntryType::OpeningStatement => 'hearing_opening_statement',
            TribunalHearingEntryType::ClosingStatement => 'hearing_closing_statement',
            TribunalHearingEntryType::JuryQuestion => 'hearing_question_posted',
            TribunalHearingEntryType::PartyAnswer, TribunalHearingEntryType::WitnessAnswer => 'hearing_response_posted',
            default => null,
        };

        if ($auditEvent) {
            TribunalCaseEventService::log($case, $auditEvent, $userId, [
                'hearing_id' => $hearing->id,
                'entry_id' => $entry->id,
                'entry_type' => $entryType->value,
                'sender_role' => $userRole,
            ]);
        }

        // If Jury Question, notify targeted participants
        if ($entryType === TribunalHearingEntryType::JuryQuestion) {
            $recipients = $this->getTargetedQuestionRecipients($case, $targetSide ?? 'both', $userId);
            if (!empty($recipients)) {
                Notification::send($recipients, new TribunalHearingQuestionNotification($hearing, $entry));
            }
        }

        return $entry->load(['sender.profile', 'relatedEvidence', 'relatedWitness']);
    }

    /**
     * Create an entry with atomic sequence numbering.
     */
    protected function createEntry(
        TribunalHearing $hearing,
        ?int $senderId,
        string $participantType,
        ?string $side,
        TribunalHearingEntryType $entryType,
        string $body,
        ?int $relatedWitnessId = null,
        ?int $relatedEvidenceId = null,
        ?string $targetSide = null,
        ?int $parentEntryId = null
    ): TribunalHearingEntry {
        $nextSeq = (TribunalHearingEntry::where('tribunal_hearing_id', $hearing->id)->max('sequence_number') ?? 0) + 1;

        return TribunalHearingEntry::create([
            'tribunal_hearing_id' => $hearing->id,
            'sender_id' => $senderId,
            'participant_type' => $participantType,
            'side' => $side,
            'entry_type' => $entryType,
            'body' => $body,
            'related_witness_id' => $relatedWitnessId,
            'related_evidence_id' => $relatedEvidenceId,
            'sequence_number' => $nextSeq,
            'target_side' => $targetSide,
            'parent_entry_id' => $parentEntryId,
        ]);
    }

    /**
     * Populate default participants: complainant, respondent, active counsel, and jury panel.
     */
    protected function populateParticipants(TribunalHearing $hearing, $panel, int $juryUserId): void
    {
        $case = $hearing->case;

        // Complainant & Respondent
        $case->loadMissing(['parties.user.profile']);
        foreach ($case->parties as $party) {
            $profile = $party->user?->profile;
            $fullName = $profile && filled($profile->first_name)
                ? trim("{$profile->first_name} {$profile->last_name}")
                : null;
            $roleFallback = $party->role->value === 'complainant' ? 'Complainant' : 'Respondent';
            $displayName = $fullName ?: $roleFallback;

            TribunalHearingParticipant::create([
                'tribunal_hearing_id' => $hearing->id,
                'user_id' => $party->user_id,
                'participant_type' => $party->role->value,
                'side' => $party->role->value,
                'display_name' => $displayName,
                'invited_by' => $juryUserId,
                'attendance_status' => 'invited',
            ]);
        }

        // Active Legal Representatives
        $activeReps = $case->activeRepresentativeAssignments()->with('representative.profile')->get();
        foreach ($activeReps as $rep) {
            $partType = $rep->side === 'complainant' ? 'complainant_representative' : 'respondent_representative';
            $profile = $rep->representative?->profile;
            $counselName = $profile && filled($profile->first_name)
                ? trim("{$profile->first_name} {$profile->last_name}")
                : 'Counsel';

            TribunalHearingParticipant::create([
                'tribunal_hearing_id' => $hearing->id,
                'user_id' => $rep->representative_user_id,
                'participant_type' => $partType,
                'side' => $rep->side,
                'display_name' => "{$counselName} (Counsel)",
                'invited_by' => $juryUserId,
                'attendance_status' => 'invited',
            ]);
        }

        // Jury Panel
        $panelName = $panel->panel_name ?? $panel->name ?? 'Jury Panel';
        TribunalHearingParticipant::create([
            'tribunal_hearing_id' => $hearing->id,
            'user_id' => $panel->login_user_id,
            'participant_type' => 'jury_panel',
            'side' => 'neutral',
            'display_name' => "{$panelName} (Jury Panel)",
            'invited_by' => $juryUserId,
            'attendance_status' => 'confirmed',
        ]);
    }

    /**
     * Get case participants to notify.
     *
     * @return User[]
     */
    public function getHearingNotificationRecipients(TribunalCase $case, ?int $excludeUserId = null): array
    {
        $userIds = [];

        foreach ($case->parties as $party) {
            $userIds[] = $party->user_id;
        }

        foreach ($case->activeRepresentativeAssignments as $rep) {
            $userIds[] = $rep->representative_user_id;
        }

        $userIds = array_values(array_unique(array_filter($userIds, fn ($id) => $id && $id !== $excludeUserId)));

        return User::whereIn('id', $userIds)->get()->all();
    }

    /**
     * Get targeted recipients for a question (complainant, respondent, or both).
     *
     * @return User[]
     */
    public function getTargetedQuestionRecipients(TribunalCase $case, string $targetSide, ?int $excludeUserId = null): array
    {
        $userIds = [];

        if ($targetSide === 'complainant' || $targetSide === 'both') {
            $compParty = $case->parties()->where('role', 'complainant')->first();
            if ($compParty) {
                $userIds[] = $compParty->user_id;
            }
            $compReps = $case->activeRepresentativeAssignments()
                ->where('side', 'complainant')
                ->pluck('representative_user_id')
                ->all();
            $userIds = array_merge($userIds, $compReps);
        }

        if ($targetSide === 'respondent' || $targetSide === 'both') {
            $respParty = $case->parties()->where('role', 'respondent')->first();
            if ($respParty) {
                $userIds[] = $respParty->user_id;
            }
            $respReps = $case->activeRepresentativeAssignments()
                ->where('side', 'respondent')
                ->pluck('representative_user_id')
                ->all();
            $userIds = array_merge($userIds, $respReps);
        }

        $userIds = array_values(array_unique(array_filter($userIds, fn ($id) => $id && $id !== $excludeUserId)));

        return User::whereIn('id', $userIds)->get()->all();
    }
}
