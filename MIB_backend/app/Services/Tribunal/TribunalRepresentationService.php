<?php

namespace App\Services\Tribunal;

use App\Enums\ProfessionalType;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\TribunalConversationType;
use App\Enums\TribunalPartyRole;
use App\Enums\TribunalRepresentationRequestStatus;
use App\Enums\TribunalRepresentativeAssignmentStatus;
use App\Models\ProfessionalVerification;
use App\Models\TribunalCase;
use App\Models\TribunalConversation;
use App\Models\TribunalRepresentationRequest;
use App\Models\TribunalRepresentativeAssignment;
use App\Models\User;
use App\Notifications\Tribunal\TribunalRepresentationAcceptedNotification;
use App\Notifications\Tribunal\TribunalRepresentationDeclinedNotification;
use App\Notifications\Tribunal\TribunalRepresentationEndedNotification;
use App\Notifications\Tribunal\TribunalRepresentationRequestedNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TribunalRepresentationService
{
    /**
     * Get verified Attorneys-at-Law available for legal representation.
     */
    public function getVerifiedRepresentatives(?TribunalCase $case = null, ?string $search = null): Collection
    {
        $query = User::query()
            ->whereDoesntHave('juryPanel')
            ->whereHas('latestProfessionalVerification', function ($q) {
                $q->where('verification_status', ProfessionalVerificationStatus::Verified)
                  ->where('profession_type', ProfessionalType::AttorneyAtLaw)
                  ->where(function ($sub) {
                      $sub->whereNull('expires_at')->orWhere('expires_at', '>', now());
                  });
            })
            ->with(['profile', 'latestProfessionalVerification']);

        if ($case) {
            // Exclude complainant and respondent of this case
            $partyUserIds = $case->parties()->pluck('user_id')->toArray();

            // Exclude assigned adjudicator
            $adjudicatorUserIds = $case->juryAssignments()
                ->whereIn('status', [
                    \App\Enums\TribunalJuryAssignmentStatus::Accepted,
                    \App\Enums\TribunalJuryAssignmentStatus::Invited,
                ])
                ->pluck('juror_id')
                ->toArray();

            $excludeIds = array_unique(array_merge($partyUserIds, $adjudicatorUserIds));
            if (!empty($excludeIds)) {
                $query->whereNotIn('id', $excludeIds);
            }
        }

        if (!empty($search)) {
            $term = trim($search);
            $query->where(function ($q) use ($term) {
                $q->where('email', 'like', "%{$term}%")
                  ->orWhereHas('profile', function ($pq) use ($term) {
                      $pq->where('first_name', 'like', "%{$term}%")
                         ->orWhere('last_name', 'like', "%{$term}%");
                  })
                  ->orWhereHas('latestProfessionalVerification', function ($vq) use ($term) {
                      $vq->where('issuing_authority', 'like', "%{$term}%")
                         ->orWhere('registration_number', 'like', "%{$term}%")
                         ->orWhere('enrollment_number', 'like', "%{$term}%");
                  });
            });
        }

        return $query->get();
    }

    /**
     * Send a legal representation request from a case party to a verified attorney.
     */
    public function requestRepresentation(
        TribunalCase $case,
        int $clientId,
        int $representativeUserId,
        ?string $message = null
    ): TribunalRepresentationRequest {
        // Determine party side
        $side = null;
        if ($case->isComplainant($clientId)) {
            $side = TribunalPartyRole::Complainant;
        } elseif ($case->isRespondent($clientId)) {
            $side = TribunalPartyRole::Respondent;
        } else {
            abort(403, 'Only a complainant or respondent may request representation.');
        }

        // Validate representative user
        $representative = User::find($representativeUserId);
        abort_unless(
            $representative && $representative->canActAsLegalRepresentative(),
            422,
            'The selected user is not an eligible verified Attorney-at-Law.'
        );

        // Conflict check: self-representation as separate lawyer
        abort_if(
            $clientId === $representativeUserId,
            422,
            'You cannot submit a representation request to yourself.'
        );

        // Conflict check: cannot represent opponent
        $isOpponent = $case->parties()->where('user_id', $representativeUserId)->exists();
        abort_if($isOpponent, 422, 'Cannot request representation from a party involved in this dispute.');

        // Conflict check: cannot represent if assigned adjudicator in this case
        $isAdjudicator = $case->juryAssignments()
            ->where('juror_id', $representativeUserId)
            ->whereIn('status', [
                \App\Enums\TribunalJuryAssignmentStatus::Accepted,
                \App\Enums\TribunalJuryAssignmentStatus::Invited,
            ])
            ->exists();
        abort_if($isAdjudicator, 422, 'An assigned adjudicator in this case cannot act as legal representative.');

        // Conflict check: attorney cannot represent both sides in the same case
        $isOpposingRepresentative = $case->activeRepresentativeAssignments()
            ->where('representative_user_id', $representativeUserId)
            ->where('side', '!=', $side)
            ->exists();
        abort_if($isOpposingRepresentative, 422, 'This attorney is already representing the opposing party in this case.');

        // Conflict check: client already has an active representative
        $hasActiveRep = $case->activeRepresentativeAssignments()
            ->where('client_user_id', $clientId)
            ->exists();
        abort_if($hasActiveRep, 422, 'You already have an active representative for this case. End your current representation first.');

        // Prevent duplicate pending requests to the same lawyer for this case
        $existingPending = TribunalRepresentationRequest::where('tribunal_case_id', $case->id)
            ->where('client_user_id', $clientId)
            ->where('representative_user_id', $representativeUserId)
            ->where('status', TribunalRepresentationRequestStatus::Pending)
            ->exists();
        abort_if($existingPending, 422, 'You already have a pending representation request with this attorney for this case.');

        return DB::transaction(function () use ($case, $clientId, $representativeUserId, $side, $message, $representative) {
            $request = TribunalRepresentationRequest::create([
                'tribunal_case_id' => $case->id,
                'requested_by' => $clientId,
                'client_user_id' => $clientId,
                'representative_user_id' => $representativeUserId,
                'side' => $side,
                'status' => TribunalRepresentationRequestStatus::Pending,
                'message' => $message,
                'requested_at' => now(),
            ]);

            // Audit log
            TribunalCaseEventService::log($case, 'representation_requested', $clientId, [
                'request_id' => $request->id,
                'representative_user_id' => $representativeUserId,
                'side' => $side->value,
            ]);

            // Notify representative
            $representative->notify(new TribunalRepresentationRequestedNotification($case, $request));

            return $request->load(['client.profile', 'representative.profile', 'tribunalCase']);
        });
    }

    /**
     * Get representation requests received by a verified attorney.
     */
    public function getLawyerRequests(int $lawyerId, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = TribunalRepresentationRequest::query()
            ->where('representative_user_id', $lawyerId)
            ->with(['client.profile', 'representative.profile', 'tribunalCase.parties.user.profile'])
            ->latest('requested_at');

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    /**
     * Accept a representation request.
     */
    public function acceptRequest(TribunalRepresentationRequest $request, User $lawyer): TribunalRepresentativeAssignment
    {
        abort_unless(
            $lawyer->id === $request->representative_user_id,
            403,
            'You are not authorized to respond to this representation request.'
        );

        abort_unless(
            $request->isPending(),
            422,
            'This representation request is no longer pending.'
        );

        // Re-verify eligibility
        abort_unless(
            $lawyer->canActAsLegalRepresentative(),
            422,
            'Your verified Attorney-at-Law credentials are not active or have expired.'
        );

        $case = $request->tribunalCase;

        // Re-check adjudicator conflict
        abort_if(
            $case->isAssignedJuror($lawyer->id),
            422,
            'Conflict of interest: You are assigned as an adjudicator on this case.'
        );

        // Re-check opposing side conflict
        $hasOpposing = $case->activeRepresentativeAssignments()
            ->where('representative_user_id', $lawyer->id)
            ->where('side', '!=', $request->side)
            ->exists();
        abort_if($hasOpposing, 422, 'Conflict of interest: You cannot represent both parties in this dispute.');

        // Re-check client active representative
        $clientHasActive = $case->activeRepresentativeAssignments()
            ->where('client_user_id', $request->client_user_id)
            ->exists();
        abort_if($clientHasActive, 422, 'The client already has an active legal representative on this case.');

        return DB::transaction(function () use ($request, $lawyer, $case) {
            // Mark request as accepted
            $request->update([
                'status' => TribunalRepresentationRequestStatus::Accepted,
                'responded_at' => now(),
            ]);

            // Create active assignment
            $assignment = TribunalRepresentativeAssignment::create([
                'tribunal_case_id' => $case->id,
                'client_user_id' => $request->client_user_id,
                'representative_user_id' => $lawyer->id,
                'side' => $request->side,
                'representation_request_id' => $request->id,
                'status' => TribunalRepresentativeAssignmentStatus::Active,
                'accepted_at' => now(),
            ]);

            // Cancel any other pending requests for this client on this case
            TribunalRepresentationRequest::where('tribunal_case_id', $case->id)
                ->where('client_user_id', $request->client_user_id)
                ->where('id', '!=', $request->id)
                ->where('status', TribunalRepresentationRequestStatus::Pending)
                ->update([
                    'status' => TribunalRepresentationRequestStatus::Cancelled,
                    'responded_at' => now(),
                ]);

            // Provision private client-representative conversation
            TribunalConversation::firstOrCreate(
                [
                    'tribunal_case_id' => $case->id,
                    'client_user_id' => $request->client_user_id,
                    'representative_user_id' => $lawyer->id,
                ],
                [
                    'type' => $request->side === TribunalPartyRole::Complainant
                        ? TribunalConversationType::ComplainantRepresentative
                        : TribunalConversationType::RespondentRepresentative,
                    'active' => true,
                ]
            );

            // Audit log
            TribunalCaseEventService::log($case, 'representation_accepted', $lawyer->id, [
                'request_id' => $request->id,
                'assignment_id' => $assignment->id,
                'representative_user_id' => $lawyer->id,
                'side' => $request->side instanceof TribunalPartyRole ? $request->side->value : (string) $request->side,
            ]);

            // Notify client
            $request->client?->notify(new TribunalRepresentationAcceptedNotification($case, $assignment));

            return $assignment->load(['client.profile', 'representative.profile', 'tribunalCase']);
        });
    }

    /**
     * Decline a representation request.
     */
    public function declineRequest(
        TribunalRepresentationRequest $request,
        User $lawyer,
        string $reason
    ): TribunalRepresentationRequest {
        abort_unless(
            $lawyer->id === $request->representative_user_id,
            403,
            'You are not authorized to respond to this representation request.'
        );

        abort_unless(
            $request->isPending(),
            422,
            'This representation request is no longer pending.'
        );

        return DB::transaction(function () use ($request, $lawyer, $reason) {
            $request->update([
                'status' => TribunalRepresentationRequestStatus::Declined,
                'decline_reason' => $reason,
                'responded_at' => now(),
            ]);

            // Audit log
            TribunalCaseEventService::log($request->tribunalCase, 'representation_declined', $lawyer->id, [
                'request_id' => $request->id,
                'representative_user_id' => $lawyer->id,
            ]);

            // Notify client
            $request->client?->notify(new TribunalRepresentationDeclinedNotification($request->tribunalCase, $request));

            return $request->load(['client.profile', 'representative.profile', 'tribunalCase']);
        });
    }

    /**
     * End an active representation assignment.
     */
    public function endRepresentation(TribunalCase $case, User $user, ?string $reason = null): TribunalRepresentativeAssignment
    {
        $assignment = $case->activeRepresentativeAssignments()
            ->where(function ($q) use ($user) {
                $q->where('client_user_id', $user->id)
                  ->orWhere('representative_user_id', $user->id);
            })
            ->first();

        abort_unless($assignment, 403, 'No active legal representation assignment found for you in this case.');

        return DB::transaction(function () use ($case, $assignment, $user, $reason) {
            $assignment->update([
                'status' => TribunalRepresentativeAssignmentStatus::Ended,
                'ended_at' => now(),
                'ended_by' => $user->id,
                'end_reason' => $reason,
            ]);

            // Deactivate associated conversation
            TribunalConversation::where('tribunal_case_id', $case->id)
                ->where('client_user_id', $assignment->client_user_id)
                ->where('representative_user_id', $assignment->representative_user_id)
                ->update(['active' => false]);

            // Audit log
            TribunalCaseEventService::log($case, 'representation_ended', $user->id, [
                'assignment_id' => $assignment->id,
                'ended_by' => $user->id,
                'reason' => $reason,
            ]);

            // Notify the other party in representation
            $otherUserId = $user->id === $assignment->client_user_id 
                ? $assignment->representative_user_id 
                : $assignment->client_user_id;

            User::find($otherUserId)?->notify(new TribunalRepresentationEndedNotification($case, $assignment, $reason));

            return $assignment->load(['client.profile', 'representative.profile', 'tribunalCase']);
        });
    }

    /**
     * Get all cases actively represented by an attorney.
     */
    public function getRepresentedCases(int $lawyerId): Collection
    {
        return TribunalCase::query()
            ->whereHas('representativeAssignments', function ($q) use ($lawyerId) {
                $q->where('representative_user_id', $lawyerId)
                  ->where('status', TribunalRepresentativeAssignmentStatus::Active);
            })
            ->with([
                'creator.profile',
                'parties.user.profile',
                'response',
                'activeRepresentativeAssignments' => fn($q) => $q->where('representative_user_id', $lawyerId),
            ])
            ->latest('submitted_at')
            ->get();
    }
}
