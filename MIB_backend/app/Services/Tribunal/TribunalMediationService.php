<?php

namespace App\Services\Tribunal;

use App\Enums\TribunalCaseStatus;
use App\Enums\TribunalMediationInitiationType;
use App\Enums\TribunalMediationStatus;
use App\Enums\TribunalSettlementProposalStatus;
use App\Models\TribunalCase;
use App\Models\TribunalCaseParty;
use App\Models\TribunalMediation;
use App\Models\TribunalMediationConsent;
use App\Models\TribunalSettlementAcceptance;
use App\Models\TribunalSettlementAgreement;
use App\Models\TribunalSettlementProposal;
use App\Models\User;
use App\Notifications\Tribunal\TribunalMediationFailedNotification;
use App\Notifications\Tribunal\TribunalMediationOfferedNotification;
use App\Notifications\Tribunal\TribunalMediationRequestedNotification;
use App\Notifications\Tribunal\TribunalMediationResponseNotification;
use App\Notifications\Tribunal\TribunalMediationStartedNotification;
use App\Notifications\Tribunal\TribunalSettlementAcceptedNotification;
use App\Notifications\Tribunal\TribunalSettlementProposalNotification;
use App\Notifications\Tribunal\TribunalSettlementReachedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TribunalMediationService
{
    public function __construct(
        protected TribunalCaseRoomService $roomService
    ) {
    }

    /**
     * Request mediation (Complainant or Respondent only).
     */
    public function requestMediation(TribunalCase $case, int $userId): TribunalMediation
    {
        $role = $case->getUserCaseRole($userId);
        if ($role !== 'complainant' && $role !== 'respondent') {
            abort(403, 'Only a principal complainant or respondent may request mediation.');
        }

        // Check if there is an active/open mediation
        $existing = $case->mediations()
            ->whereIn('status', [
                TribunalMediationStatus::Offered,
                TribunalMediationStatus::AwaitingConsent,
                TribunalMediationStatus::Active,
            ])
            ->first();

        if ($existing) {
            abort(422, 'There is already an active or pending mediation process for this case.');
        }

        $partySide = $role; // 'complainant' or 'respondent'
        $otherSide = $partySide === 'complainant' ? 'respondent' : 'complainant';

        $otherParty = $case->parties()->where('role', $otherSide)->first();
        if (!$otherParty) {
            abort(422, 'Cannot initiate mediation: opposing party is not registered.');
        }

        return DB::transaction(function () use ($case, $userId, $partySide, $otherParty, $otherSide) {
            $mediation = TribunalMediation::create([
                'tribunal_case_id' => $case->id,
                'initiated_by' => $userId,
                'initiation_type' => TribunalMediationInitiationType::PartyRequest,
                'status' => TribunalMediationStatus::AwaitingConsent,
                'previous_case_status' => $case->status instanceof \BackedEnum ? $case->status->value : (string) $case->status,
                'offered_at' => now(),
            ]);

            // Requesting party consent is automatically accepted
            TribunalMediationConsent::create([
                'tribunal_mediation_id' => $mediation->id,
                'user_id' => $userId,
                'side' => $partySide,
                'response' => 'accepted',
                'responded_at' => now(),
            ]);

            // Opposing party consent is pending
            TribunalMediationConsent::create([
                'tribunal_mediation_id' => $mediation->id,
                'user_id' => $otherParty->user_id,
                'side' => $otherSide,
                'response' => 'pending',
                'responded_at' => null,
            ]);

            TribunalCaseEventService::log(
                $case,
                'mediation_requested',
                $userId,
                ['mediation_id' => $mediation->id, 'requested_by_side' => $partySide]
            );

            $initiator = User::find($userId);
            $recipients = $this->roomService->getCaseParticipants($case, $userId);
            if (!empty($recipients)) {
                Notification::send($recipients, new TribunalMediationRequestedNotification($case, $mediation, $initiator));
            }

            return $mediation->load(['consents.user.profile']);
        });
    }

    /**
     * Offer mediation (Active Adjudicator or Jury Panel only).
     */
    public function offerMediation(TribunalCase $case, int $userId): TribunalMediation
    {
        $role = $case->getUserCaseRole($userId);
        if ($role !== 'adjudicator' && $role !== 'jury_panel') {
            abort(403, 'Only the assigned Tribunal Jury Panel or Adjudicator can offer mediation.');
        }

        $existing = $case->mediations()
            ->whereIn('status', [
                TribunalMediationStatus::Offered,
                TribunalMediationStatus::AwaitingConsent,
                TribunalMediationStatus::Active,
            ])
            ->first();

        if ($existing) {
            abort(422, 'There is already an active or pending mediation process for this case.');
        }

        $complainant = $case->parties()->where('role', 'complainant')->first();
        $respondent = $case->parties()->where('role', 'respondent')->first();

        if (!$complainant || !$respondent) {
            abort(422, 'Cannot offer mediation: both complainant and respondent must be registered.');
        }

        return DB::transaction(function () use ($case, $userId, $role, $complainant, $respondent) {
            $mediation = TribunalMediation::create([
                'tribunal_case_id' => $case->id,
                'initiated_by' => $userId,
                'initiation_type' => TribunalMediationInitiationType::AdjudicatorOffer,
                'status' => TribunalMediationStatus::Offered,
                'previous_case_status' => $case->status instanceof \BackedEnum ? $case->status->value : (string) $case->status,
                'offered_at' => now(),
            ]);

            // Pending consent for both parties
            TribunalMediationConsent::create([
                'tribunal_mediation_id' => $mediation->id,
                'user_id' => $complainant->user_id,
                'side' => 'complainant',
                'response' => 'pending',
                'responded_at' => null,
            ]);

            TribunalMediationConsent::create([
                'tribunal_mediation_id' => $mediation->id,
                'user_id' => $respondent->user_id,
                'side' => 'respondent',
                'response' => 'pending',
                'responded_at' => null,
            ]);

            if ($role === 'jury_panel') {
                $panel = User::find($userId)?->juryPanel;
                TribunalCaseEventService::log(
                    $case,
                    'jury_panel_mediation_offered',
                    $userId,
                    [
                        'jury_panel_id' => $panel?->id,
                        'panel_code' => $panel?->panel_code,
                        'mediation_id' => $mediation->id,
                    ]
                );
            } else {
                TribunalCaseEventService::log(
                    $case,
                    'mediation_offered',
                    $userId,
                    ['mediation_id' => $mediation->id]
                );
            }

            $adjudicator = User::find($userId);
            $recipients = $this->roomService->getCaseParticipants($case, $userId);
            if (!empty($recipients)) {
                Notification::send($recipients, new TribunalMediationOfferedNotification($case, $mediation, $adjudicator));
            }

            return $mediation->load(['consents.user.profile']);
        });
    }

    /**
     * Respond to mediation request/offer (Complainant or Respondent only).
     */
    public function respondToMediation(TribunalMediation $mediation, int $userId, string $response): TribunalMediation
    {
        $case = $mediation->tribunalCase;
        $role = $case->getUserCaseRole($userId);

        if ($role !== 'complainant' && $role !== 'respondent') {
            abort(403, 'Only principal parties can accept or decline mediation. Legal representatives cannot provide binding consent.');
        }

        if (!in_array($response, ['accepted', 'declined'])) {
            abort(422, 'Response must be accepted or declined.');
        }

        if (!in_array($mediation->status, [TribunalMediationStatus::Offered, TribunalMediationStatus::AwaitingConsent])) {
            abort(422, 'Mediation is not awaiting consent.');
        }

        $consent = $mediation->consents()->where('user_id', $userId)->first();
        if (!$consent) {
            abort(403, 'You do not have a pending mediation consent invitation.');
        }

        if ($consent->response !== 'pending') {
            abort(422, 'You have already responded to this mediation offer.');
        }

        return DB::transaction(function () use ($mediation, $case, $userId, $consent, $response) {
            $consent->update([
                'response' => $response,
                'responded_at' => now(),
            ]);

            $responder = User::find($userId);

            if ($response === 'declined') {
                $mediation->update([
                    'status' => TribunalMediationStatus::Declined,
                    'ended_at' => now(),
                    'failure_reason' => 'Declined by party',
                ]);

                // Case status returns to previous_case_status
                $prevStatus = $mediation->previous_case_status;
                if ($prevStatus) {
                    $case->update(['status' => TribunalCaseStatus::tryFrom($prevStatus) ?? $prevStatus]);
                }

                TribunalCaseEventService::log(
                    $case,
                    'mediation_declined',
                    $userId,
                    ['mediation_id' => $mediation->id]
                );

                $recipients = $this->roomService->getCaseParticipants($case, $userId);
                if (!empty($recipients)) {
                    Notification::send($recipients, new TribunalMediationResponseNotification($case, $mediation, $responder, 'declined'));
                }
            } else {
                TribunalCaseEventService::log(
                    $case,
                    'mediation_accepted',
                    $userId,
                    ['mediation_id' => $mediation->id]
                );

                $recipients = $this->roomService->getCaseParticipants($case, $userId);
                if (!empty($recipients)) {
                    Notification::send($recipients, new TribunalMediationResponseNotification($case, $mediation, $responder, 'accepted'));
                }

                // Check if BOTH parties have accepted
                $acceptedCount = $mediation->consents()->where('response', 'accepted')->count();
                if ($acceptedCount >= 2) {
                    $mediation->update([
                        'status' => TribunalMediationStatus::Active,
                        'started_at' => now(),
                    ]);

                    $case->update([
                        'status' => TribunalCaseStatus::Mediation,
                    ]);

                    TribunalCaseEventService::log(
                        $case,
                        'mediation_started',
                        $userId,
                        ['mediation_id' => $mediation->id]
                    );

                    $allParticipants = $this->roomService->getCaseParticipants($case);
                    if (!empty($allParticipants)) {
                        Notification::send($allParticipants, new TribunalMediationStartedNotification($case, $mediation));
                    }
                }
            }

            return $mediation->fresh(['consents.user.profile']);
        });
    }

    /**
     * Create initial settlement proposal (Principal party only).
     */
    public function createProposal(TribunalMediation $mediation, int $userId, string $terms): TribunalSettlementProposal
    {
        $case = $mediation->tribunalCase;
        $role = $case->getUserCaseRole($userId);

        if ($role !== 'complainant' && $role !== 'respondent') {
            abort(403, 'Only principal parties can submit settlement proposals. Representatives cannot bind clients.');
        }

        if ($mediation->status !== TribunalMediationStatus::Active) {
            abort(422, 'Settlement proposals can only be submitted during active mediation.');
        }

        $side = $role;

        return DB::transaction(function () use ($mediation, $case, $userId, $side, $terms) {
            $proposal = TribunalSettlementProposal::create([
                'tribunal_mediation_id' => $mediation->id,
                'proposed_by' => $userId,
                'proposed_by_side' => $side,
                'parent_proposal_id' => null,
                'version_number' => 1,
                'terms' => $terms,
                'status' => TribunalSettlementProposalStatus::Pending,
            ]);

            // The proposing party automatically accepts their own proposal
            TribunalSettlementAcceptance::create([
                'settlement_proposal_id' => $proposal->id,
                'user_id' => $userId,
                'side' => $side,
                'accepted_at' => now(),
            ]);

            TribunalCaseEventService::log(
                $case,
                'settlement_proposed',
                $userId,
                [
                    'mediation_id' => $mediation->id,
                    'proposal_id' => $proposal->id,
                    'version_number' => 1,
                ]
            );

            $proposer = User::find($userId);
            $recipients = $this->roomService->getCaseParticipants($case, $userId);
            if (!empty($recipients)) {
                Notification::send($recipients, new TribunalSettlementProposalNotification($case, $proposal, $proposer, false));
            }

            return $proposal->load(['proposer.profile', 'acceptances']);
        });
    }

    /**
     * Create counter-proposal (Principal opposing party only).
     */
    public function counterProposal(
        TribunalMediation $mediation,
        TribunalSettlementProposal $parentProposal,
        int $userId,
        string $terms
    ): TribunalSettlementProposal {
        $case = $mediation->tribunalCase;
        $role = $case->getUserCaseRole($userId);

        if ($role !== 'complainant' && $role !== 'respondent') {
            abort(403, 'Only principal parties can submit counter-proposals.');
        }

        if ($mediation->status !== TribunalMediationStatus::Active) {
            abort(422, 'Counter-proposals can only be submitted during active mediation.');
        }

        if ($parentProposal->tribunal_mediation_id !== $mediation->id) {
            abort(422, 'The parent proposal does not belong to this mediation.');
        }

        if ($parentProposal->status !== TribunalSettlementProposalStatus::Pending) {
            abort(422, 'Cannot counter a proposal that is not pending.');
        }

        $side = $role;
        if ($parentProposal->proposed_by_side === $side) {
            abort(422, 'You cannot submit a counter-proposal to your own proposal.');
        }

        return DB::transaction(function () use ($mediation, $case, $parentProposal, $userId, $side, $terms) {
            // Mark parent proposal as countered
            $parentProposal->update([
                'status' => TribunalSettlementProposalStatus::Countered,
            ]);

            $newVersion = $parentProposal->version_number + 1;

            $proposal = TribunalSettlementProposal::create([
                'tribunal_mediation_id' => $mediation->id,
                'proposed_by' => $userId,
                'proposed_by_side' => $side,
                'parent_proposal_id' => $parentProposal->id,
                'version_number' => $newVersion,
                'terms' => $terms,
                'status' => TribunalSettlementProposalStatus::Pending,
            ]);

            // The proposing party automatically accepts their own counter-proposal
            TribunalSettlementAcceptance::create([
                'settlement_proposal_id' => $proposal->id,
                'user_id' => $userId,
                'side' => $side,
                'accepted_at' => now(),
            ]);

            TribunalCaseEventService::log(
                $case,
                'settlement_countered',
                $userId,
                [
                    'mediation_id' => $mediation->id,
                    'proposal_id' => $proposal->id,
                    'parent_proposal_id' => $parentProposal->id,
                    'version_number' => $newVersion,
                ]
            );

            $proposer = User::find($userId);
            $recipients = $this->roomService->getCaseParticipants($case, $userId);
            if (!empty($recipients)) {
                Notification::send($recipients, new TribunalSettlementProposalNotification($case, $proposal, $proposer, true));
            }

            return $proposal->load(['proposer.profile', 'acceptances']);
        });
    }

    /**
     * Reject a settlement proposal (Opposing principal party only).
     */
    public function rejectProposal(TribunalSettlementProposal $proposal, int $userId): TribunalSettlementProposal
    {
        $mediation = $proposal->mediation;
        $case = $mediation->tribunalCase;
        $role = $case->getUserCaseRole($userId);

        if ($role !== 'complainant' && $role !== 'respondent') {
            abort(403, 'Only principal parties can reject proposals.');
        }

        if ($proposal->proposed_by_side === $role) {
            abort(422, 'You cannot reject your own proposal.');
        }

        if ($proposal->status !== TribunalSettlementProposalStatus::Pending) {
            abort(422, 'This proposal is no longer pending.');
        }

        $proposal->update([
            'status' => TribunalSettlementProposalStatus::Rejected,
        ]);

        TribunalCaseEventService::log(
            $case,
            'settlement_rejected',
            $userId,
            ['mediation_id' => $mediation->id, 'proposal_id' => $proposal->id]
        );

        return $proposal->fresh(['proposer.profile', 'acceptances']);
    }

    /**
     * Explicitly accept a settlement proposal (Principal party only).
     * If both parties accept the same proposal, creates immutable settlement agreement and finalizes settlement.
     */
    public function acceptProposal(TribunalSettlementProposal $proposal, int $userId): TribunalSettlementAgreement|TribunalSettlementProposal
    {
        $mediation = $proposal->mediation;
        $case = $mediation->tribunalCase;
        $role = $case->getUserCaseRole($userId);

        if ($role !== 'complainant' && $role !== 'respondent') {
            abort(403, 'Only principal parties can accept settlement proposals. Representatives cannot finalize settlement.');
        }

        if ($proposal->status !== TribunalSettlementProposalStatus::Pending) {
            abort(422, 'This proposal is not pending acceptance.');
        }

        $side = $role;

        return DB::transaction(function () use ($proposal, $mediation, $case, $userId, $side) {
            // Lock proposal row for race condition prevention
            $lockedProposal = TribunalSettlementProposal::where('id', $proposal->id)->lockForUpdate()->first();

            if ($lockedProposal->status !== TribunalSettlementProposalStatus::Pending) {
                // Already settled or handled by concurrent request
                $existingAgreement = TribunalSettlementAgreement::where('settlement_proposal_id', $lockedProposal->id)->first();
                if ($existingAgreement) {
                    return $existingAgreement;
                }
                abort(422, 'Proposal is no longer pending.');
            }

            // Create or ignore acceptance
            $acceptance = TribunalSettlementAcceptance::firstOrCreate(
                [
                    'settlement_proposal_id' => $lockedProposal->id,
                    'user_id' => $userId,
                ],
                [
                    'side' => $side,
                    'accepted_at' => now(),
                ]
            );

            TribunalCaseEventService::log(
                $case,
                'settlement_accepted',
                $userId,
                ['mediation_id' => $mediation->id, 'proposal_id' => $lockedProposal->id]
            );

            $user = User::find($userId);
            $recipients = $this->roomService->getCaseParticipants($case, $userId);
            if (!empty($recipients)) {
                Notification::send($recipients, new TribunalSettlementAcceptedNotification($case, $lockedProposal, $user));
            }

            // Check if BOTH principal parties (complainant and respondent) have accepted
            $complainantAcc = TribunalSettlementAcceptance::where('settlement_proposal_id', $lockedProposal->id)
                ->where('side', 'complainant')
                ->first();

            $respondentAcc = TribunalSettlementAcceptance::where('settlement_proposal_id', $lockedProposal->id)
                ->where('side', 'respondent')
                ->first();

            if ($complainantAcc && $respondentAcc) {
                // BOTH parties accepted the same proposal! Finalize settlement.
                $lockedProposal->update([
                    'status' => TribunalSettlementProposalStatus::Accepted,
                ]);

                // Supersede any other pending proposals in this mediation
                TribunalSettlementProposal::where('tribunal_mediation_id', $mediation->id)
                    ->where('id', '!=', $lockedProposal->id)
                    ->where('status', TribunalSettlementProposalStatus::Pending)
                    ->update(['status' => TribunalSettlementProposalStatus::Superseded]);

                // Generate agreement number: MIB-SET-YYYY-000001
                $year = now()->format('Y');
                $count = TribunalSettlementAgreement::whereYear('created_at', now()->year)->count() + 1;
                $agreementNumber = sprintf('MIB-SET-%s-%06d', $year, $count);

                $agreement = TribunalSettlementAgreement::create([
                    'tribunal_case_id' => $case->id,
                    'tribunal_mediation_id' => $mediation->id,
                    'settlement_proposal_id' => $lockedProposal->id,
                    'agreement_number' => $agreementNumber,
                    'terms_snapshot' => $lockedProposal->terms,
                    'complainant_accepted_at' => $complainantAcc->accepted_at,
                    'respondent_accepted_at' => $respondentAcc->accepted_at,
                    'finalized_at' => now(),
                ]);

                $mediation->update([
                    'status' => TribunalMediationStatus::Settled,
                    'ended_at' => now(),
                ]);

                $case->update([
                    'status' => TribunalCaseStatus::Settled,
                ]);

                TribunalCaseEventService::log(
                    $case,
                    'settlement_reached',
                    $userId,
                    [
                        'mediation_id' => $mediation->id,
                        'agreement_id' => $agreement->id,
                        'agreement_number' => $agreementNumber,
                    ]
                );

                $allParticipants = $this->roomService->getCaseParticipants($case);
                if (!empty($allParticipants)) {
                    Notification::send($allParticipants, new TribunalSettlementReachedNotification($case, $agreement));
                }

                return $agreement;
            }

            return $lockedProposal->load(['proposer.profile', 'acceptances']);
        });
    }

    /**
     * End mediation as failed (Adjudicator, Jury Panel, or principal party).
     */
    public function endMediation(TribunalMediation $mediation, int $userId, string $reason): TribunalMediation
    {
        $case = $mediation->tribunalCase;
        $role = $case->getUserCaseRole($userId);

        if ($role !== 'complainant' && $role !== 'respondent' && $role !== 'adjudicator' && $role !== 'jury_panel') {
            abort(403, 'Only the active adjudicator, jury panel, or a principal party can conclude mediation.');
        }

        if (!in_array($mediation->status, [TribunalMediationStatus::Active, TribunalMediationStatus::AwaitingConsent, TribunalMediationStatus::Offered])) {
            abort(422, 'Cannot end mediation that is already settled, failed, or cancelled.');
        }

        return DB::transaction(function () use ($mediation, $case, $userId, $role, $reason) {
            $mediation->update([
                'status' => TribunalMediationStatus::Failed,
                'failure_reason' => $reason,
                'ended_at' => now(),
            ]);

            // Restore previous case status
            $prevStatus = $mediation->previous_case_status;
            if ($prevStatus) {
                $case->update([
                    'status' => TribunalCaseStatus::tryFrom($prevStatus) ?? $prevStatus,
                ]);
            }

            if ($role === 'jury_panel') {
                $panel = User::find($userId)?->juryPanel;
                TribunalCaseEventService::log(
                    $case,
                    'jury_panel_mediation_failed',
                    $userId,
                    [
                        'jury_panel_id' => $panel?->id,
                        'panel_code' => $panel?->panel_code,
                        'mediation_id' => $mediation->id,
                        'reason' => $reason,
                    ]
                );
            } else {
                TribunalCaseEventService::log(
                    $case,
                    'mediation_failed',
                    $userId,
                    [
                        'mediation_id' => $mediation->id,
                        'reason' => $reason,
                    ]
                );
            }

            $recipients = $this->roomService->getCaseParticipants($case, $userId);
            if (!empty($recipients)) {
                Notification::send($recipients, new TribunalMediationFailedNotification($case, $mediation, $reason));
            }

            return $mediation->fresh(['consents.user.profile']);
        });
    }
}
