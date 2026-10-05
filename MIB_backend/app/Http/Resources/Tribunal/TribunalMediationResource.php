<?php

namespace App\Http\Resources\Tribunal;

use App\Models\TribunalMediation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TribunalMediationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var TribunalMediation $med */
        $med = $this->resource;

        $initiator = $med->initiator;
        $initiatorName = $initiator?->profile?->first_name
            ? "{$initiator->profile->first_name} {$initiator->profile->last_name}"
            : ($initiator?->email ?? 'Participant');

        $userId = auth()->id();
        $myConsent = $userId ? $med->consents->firstWhere('user_id', $userId) : null;

        // Can current user consent? Must be complainant or respondent, status in offered/awaiting_consent, and haven't responded yet
        $case = $med->tribunalCase;
        $isPrincipalParty = $case && ($case->isComplainant($userId) || $case->isRespondent($userId));
        $canConsent = $isPrincipalParty
            && in_array($med->status, [\App\Enums\TribunalMediationStatus::Offered, \App\Enums\TribunalMediationStatus::AwaitingConsent])
            && (!$myConsent || $myConsent->response === 'pending');

        return [
            'id' => $med->id,
            'tribunal_case_id' => $med->tribunal_case_id,
            'initiated_by' => $med->initiated_by,
            'initiator_name' => $initiatorName,
            'initiation_type' => $med->initiation_type?->value ?? (string) $med->initiation_type,
            'status' => $med->status?->value ?? (string) $med->status,
            'previous_case_status' => $med->previous_case_status,
            'offered_at' => $med->offered_at?->toIso8601String(),
            'started_at' => $med->started_at?->toIso8601String(),
            'ended_at' => $med->ended_at?->toIso8601String(),
            'failure_reason' => $med->failure_reason,
            'consents' => TribunalMediationConsentResource::collection($this->whenLoaded('consents', $med->consents)),
            'proposals' => TribunalSettlementProposalResource::collection($this->whenLoaded('proposals', $med->proposals)),
            'settlement_agreement' => new TribunalSettlementAgreementResource($this->whenLoaded('settlementAgreement', $med->settlementAgreement)),
            'my_consent' => $myConsent ? new TribunalMediationConsentResource($myConsent) : null,
            'can_consent' => $canConsent,
            'created_at' => $med->created_at?->toIso8601String(),
            'updated_at' => $med->updated_at?->toIso8601String(),
        ];
    }
}
