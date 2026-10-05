<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TribunalCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'case_number' => $this->case_number,

            'title' => $this->title,

            'category' => $this->category,

            'description' => $this->description,

            'requested_resolution' => $this->requested_resolution,

            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,

            'severity' => $this->severity,

            'submitted_at' => $this->submitted_at,

            'parties' => $this->parties->map(function ($party) {
                $profile = $party->user?->profile;
                $name = $profile?->full_name 
                    ?: trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''));

                return [
                    'id' => $party->id,

                    'role' => $party->role instanceof \BackedEnum ? $party->role->value : $party->role,

                    'user' => [
                        'id' => $party->user?->id,

                        'name' => $name ?: ('User #' . $party->user?->id),
                    ],
                ];
            }),

            'response' => $this->relationLoaded('response') && $this->response ? [
                'id' => $this->response->id,
                'acknowledgement_at' => $this->response->acknowledgement_at,
                'position' => $this->response->position instanceof \BackedEnum ? $this->response->position->value : $this->response->position,
                'response_text' => $this->response->response_text,
                'submitted_at' => $this->response->submitted_at,
            ] : null,

            'evidence' => $this->relationLoaded('evidence')
                ? \App\Http\Resources\Tribunal\TribunalEvidenceResource::collection($this->evidence)
                : [],

            'jury' => $this->resolveJurySummary(),

            'representation' => $this->resolveRepresentationSummary(auth()->id()),

            'current_user_role' => $this->resolveCurrentUserRole(auth()->id()),

            'case_room' => $this->relationLoaded('caseRoom') && $this->caseRoom ? [
                'id' => $this->caseRoom->id,
                'status' => $this->caseRoom->status instanceof \BackedEnum ? $this->caseRoom->status->value : $this->caseRoom->status,
                'opened_at' => $this->caseRoom->opened_at,
            ] : null,

            'active_mediation' => $this->relationLoaded('activeMediation') && $this->activeMediation ? [
                'id' => $this->activeMediation->id,
                'status' => $this->activeMediation->status instanceof \BackedEnum ? $this->activeMediation->status->value : $this->activeMediation->status,
                'initiation_type' => $this->activeMediation->initiation_type instanceof \BackedEnum ? $this->activeMediation->initiation_type->value : $this->activeMediation->initiation_type,
                'started_at' => $this->activeMediation->started_at,
            ] : null,

            'settlement_agreement' => $this->relationLoaded('settlementAgreement') && $this->settlementAgreement ? [
                'id' => $this->settlementAgreement->id,
                'agreement_number' => $this->settlementAgreement->agreement_number,
                'finalized_at' => $this->settlementAgreement->finalized_at,
            ] : null,
        ];
    }

    private function resolveJurySummary(): array
    {
        $acceptedAssignment = $this->relationLoaded('acceptedJuryAssignment')
            ? $this->acceptedJuryAssignment
            : $this->acceptedJuryAssignment()->with('juror.profile')->first();

        if ($acceptedAssignment) {
            $profile = $acceptedAssignment->juror?->profile;
            $name = $profile?->full_name 
                ?: trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''));

            return [
                'status' => 'assigned',
                'label' => 'Tribunal Member Assigned',
                'juror_name' => $name ?: ('Member #' . $acceptedAssignment->juror_id),
                'role' => $acceptedAssignment->role instanceof \BackedEnum ? $acceptedAssignment->role->value : $acceptedAssignment->role,
                'assigned_at' => $acceptedAssignment->assigned_at,
                'responded_at' => $acceptedAssignment->responded_at,
            ];
        }

        $currentAssignment = $this->relationLoaded('currentJuryAssignment')
            ? $this->currentJuryAssignment
            : $this->currentJuryAssignment()->first();

        if ($currentAssignment) {
            return [
                'status' => 'selection_in_progress',
                'label' => 'Jury selection in progress',
                'juror_name' => null,
                'role' => null,
            ];
        }

        if ($this->response && $this->response->submitted_at !== null) {
            return [
                'status' => 'awaiting_assignment',
                'label' => 'Awaiting jury assignment',
                'juror_name' => null,
                'role' => null,
            ];
        }

        return [
            'status' => 'pending_response',
            'label' => 'Awaiting respondent response',
            'juror_name' => null,
            'role' => null,
        ];
    }

    private function resolveCurrentUserRole(?int $userId): string
    {
        if (!$userId) return 'none';

        if ($this->isComplainant($userId)) return 'complainant';
        if ($this->isRespondent($userId)) return 'respondent';
        if ($this->isAcceptedRepresentative($userId)) return 'representative';
        if ($this->isAcceptedJuror($userId)) return 'juror';

        return 'none';
    }

    private function resolveRepresentationSummary(?int $userId): array
    {
        $hasActiveRep = false;
        $activeAssignment = null;
        $pendingRequest = null;
        $isRep = false;
        $representedSide = null;

        if ($userId) {
            $isRep = $this->isAcceptedRepresentative($userId);
            if ($isRep) {
                $assignment = $this->relationLoaded('activeRepresentativeAssignments')
                    ? $this->activeRepresentativeAssignments->firstWhere('representative_user_id', $userId)
                    : $this->activeRepresentativeAssignments()->where('representative_user_id', $userId)->first();

                $representedSide = $assignment?->side instanceof \BackedEnum ? $assignment->side->value : $assignment?->side;
            }

            $activeAssignmentModel = $this->relationLoaded('activeRepresentativeAssignments')
                ? $this->activeRepresentativeAssignments->firstWhere('client_user_id', $userId)
                : $this->activeRepresentativeAssignments()
                    ->where('client_user_id', $userId)
                    ->with('representative.profile')
                    ->first();

            if ($activeAssignmentModel) {
                $hasActiveRep = true;
                $activeAssignment = [
                    'id' => $activeAssignmentModel->id,
                    'representative_user_id' => $activeAssignmentModel->representative_user_id,
                    'representative_name' => $activeAssignmentModel->representative?->profile?->full_name 
                        ?: ('Representative #' . $activeAssignmentModel->representative_user_id),
                    'side' => $activeAssignmentModel->side instanceof \BackedEnum ? $activeAssignmentModel->side->value : $activeAssignmentModel->side,
                    'accepted_at' => $activeAssignmentModel->accepted_at?->toISOString(),
                ];
            } else {
                $pendingRequestModel = $this->relationLoaded('representationRequests')
                    ? $this->representationRequests
                        ->where('client_user_id', $userId)
                        ->first(fn ($r) => ($r->status instanceof \BackedEnum ? $r->status->value : $r->status) === 'pending')
                    : $this->representationRequests()
                        ->where('client_user_id', $userId)
                        ->where('status', \App\Enums\TribunalRepresentationRequestStatus::Pending)
                        ->with('representative.profile')
                        ->first();

                if ($pendingRequestModel) {
                    $pendingRequest = [
                        'id' => $pendingRequestModel->id,
                        'representative_user_id' => $pendingRequestModel->representative_user_id,
                        'representative_name' => $pendingRequestModel->representative?->profile?->full_name 
                            ?: ('Representative #' . $pendingRequestModel->representative_user_id),
                        'status' => $pendingRequestModel->status instanceof \BackedEnum ? $pendingRequestModel->status->value : $pendingRequestModel->status,
                        'requested_at' => $pendingRequestModel->requested_at?->toISOString(),
                    ];
                }
            }
        }

        return [
            'has_active_representation' => $hasActiveRep,
            'active_assignment' => $activeAssignment,
            'pending_request' => $pendingRequest,
            'is_representative_for_case' => $isRep,
            'my_represented_party' => $representedSide,
        ];
    }
}
