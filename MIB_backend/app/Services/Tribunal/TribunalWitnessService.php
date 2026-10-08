<?php

namespace App\Services\Tribunal;

use App\Enums\TribunalHearingEntryType;
use App\Enums\TribunalHearingStatus;
use App\Enums\TribunalWitnessStatus;
use App\Models\TribunalCase;
use App\Models\TribunalHearing;
use App\Models\TribunalHearingEntry;
use App\Models\TribunalWitness;
use App\Models\User;
use App\Notifications\Tribunal\TribunalWitnessApprovedNotification;
use App\Notifications\Tribunal\TribunalWitnessProposedNotification;
use App\Notifications\Tribunal\TribunalWitnessRejectedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TribunalWitnessService
{
    /**
     * Propose a witness for a case.
     * Proposing side is strictly derived from the caller's case side.
     */
    public function proposeWitness(TribunalCase $case, int $userId, array $data): TribunalWitness
    {
        $side = $case->getUserCaseSide($userId);
        if (!$side) {
            abort(403, 'Unauthorized. Only active parties and their authorized representatives can propose witnesses.');
        }

        return DB::transaction(function () use ($case, $userId, $side, $data) {
            $witness = TribunalWitness::create([
                'tribunal_case_id' => $case->id,
                'tribunal_hearing_id' => $data['tribunal_hearing_id'] ?? null,
                'proposed_by' => $userId,
                'side' => $side,
                'witness_user_id' => $data['witness_user_id'] ?? null,
                'witness_name' => $data['witness_name'],
                'witness_email' => $data['witness_email'] ?? null,
                'relationship_to_case' => $data['relationship_to_case'] ?? null,
                'statement_summary' => $data['statement_summary'] ?? null,
                'status' => TribunalWitnessStatus::Proposed,
            ]);

            TribunalCaseEventService::log($case, 'witness_proposed', $userId, [
                'witness_id' => $witness->id,
                'witness_name' => $witness->witness_name,
                'side' => $side,
            ]);

            // Notify assigned Jury Panel if present
            $panelAssignment = $case->currentJuryPanelAssignment;
            if ($panelAssignment && $panelAssignment->juryPanel?->login_user_id) {
                $juryUser = User::find($panelAssignment->juryPanel->login_user_id);
                if ($juryUser) {
                    Notification::send($juryUser, new TribunalWitnessProposedNotification($witness));
                }
            }

            return $witness->load('proposer.profile');
        });
    }

    /**
     * Approve a proposed witness.
     * Only the assigned active Jury Panel can approve.
     */
    public function approveWitness(TribunalWitness $witness, int $userId): TribunalWitness
    {
        $case = $witness->case;
        if (!$case->isAssignedJuryPanelUser($userId)) {
            abort(403, 'Unauthorized. Only the assigned Jury Panel can approve witnesses.');
        }

        return DB::transaction(function () use ($witness, $case, $userId) {
            $witness->update([
                'status' => TribunalWitnessStatus::Approved,
                'approved_by_panel_at' => now(),
                'rejected_reason' => null,
            ]);

            TribunalCaseEventService::log($case, 'witness_approved', $userId, [
                'witness_id' => $witness->id,
                'witness_name' => $witness->witness_name,
            ]);

            // Notify proposer
            if ($witness->proposer) {
                Notification::send($witness->proposer, new TribunalWitnessApprovedNotification($witness));
            }

            return $witness;
        });
    }

    /**
     * Reject a proposed witness with a reason.
     * Only the assigned active Jury Panel can reject.
     */
    public function rejectWitness(TribunalWitness $witness, int $userId, string $reason): TribunalWitness
    {
        $case = $witness->case;
        if (!$case->isAssignedJuryPanelUser($userId)) {
            abort(403, 'Unauthorized. Only the assigned Jury Panel can reject witnesses.');
        }

        if (trim($reason) === '') {
            abort(422, 'A rejection reason is required.');
        }

        return DB::transaction(function () use ($witness, $case, $userId, $reason) {
            $witness->update([
                'status' => TribunalWitnessStatus::Rejected,
                'rejected_reason' => $reason,
            ]);

            TribunalCaseEventService::log($case, 'witness_rejected', $userId, [
                'witness_id' => $witness->id,
                'witness_name' => $witness->witness_name,
                'reason' => $reason,
            ]);

            if ($witness->proposer) {
                Notification::send($witness->proposer, new TribunalWitnessRejectedNotification($witness, $reason));
            }

            return $witness;
        });
    }

    /**
     * Record structured witness testimony during an active hearing.
     */
    public function recordTestimony(
        TribunalHearing $hearing,
        TribunalWitness $witness,
        int $userId,
        string $testimonyBody
    ): TribunalHearingEntry {
        if ($hearing->status !== TribunalHearingStatus::Active) {
            abort(422, 'Testimony can only be recorded during an active hearing session.');
        }

        $case = $hearing->case;
        if ($witness->tribunal_case_id !== $case->id) {
            abort(422, 'Witness does not belong to this tribunal case.');
        }

        if (!$witness->isApproved() && !$witness->isTestified()) {
            abort(422, 'Witness has not been approved by the Jury Panel to testify.');
        }

        // Must be authorized participant, lawyer, or jury panel
        $userRole = $case->getUserCaseRole($userId);
        if (!$userRole) {
            abort(403, 'Unauthorized to record witness testimony.');
        }

        return DB::transaction(function () use ($hearing, $witness, $case, $userId, $testimonyBody) {
            $nextSeq = (TribunalHearingEntry::where('tribunal_hearing_id', $hearing->id)->max('sequence_number') ?? 0) + 1;

            $entry = TribunalHearingEntry::create([
                'tribunal_hearing_id' => $hearing->id,
                'sender_id' => $userId,
                'participant_type' => 'witness',
                'side' => $witness->side,
                'entry_type' => TribunalHearingEntryType::WitnessTestimony,
                'body' => "Testimony of {$witness->witness_name} ({$witness->side}): {$testimonyBody}",
                'related_witness_id' => $witness->id,
                'sequence_number' => $nextSeq,
            ]);

            $witness->update([
                'status' => TribunalWitnessStatus::Testified,
                'tribunal_hearing_id' => $hearing->id,
            ]);

            TribunalCaseEventService::log($case, 'witness_testified', $userId, [
                'hearing_id' => $hearing->id,
                'witness_id' => $witness->id,
                'witness_name' => $witness->witness_name,
                'side' => $witness->side,
                'entry_id' => $entry->id,
            ]);

            return $entry->load(['relatedWitness', 'sender.profile']);
        });
    }
}
