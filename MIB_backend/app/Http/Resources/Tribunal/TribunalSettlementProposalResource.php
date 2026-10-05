<?php

namespace App\Http\Resources\Tribunal;

use App\Models\TribunalSettlementProposal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TribunalSettlementProposalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var TribunalSettlementProposal $prop */
        $prop = $this->resource;

        $proposer = $prop->proposer;
        $proposerName = $proposer?->profile?->first_name
            ? "{$proposer->profile->first_name} {$proposer->profile->last_name}"
            : ($proposer?->email ?? 'Party');

        $isMine = auth()->id() === $prop->proposed_by;

        $acceptances = $prop->acceptances->map(function ($acc) {
            return [
                'id' => $acc->id,
                'user_id' => $acc->user_id,
                'side' => $acc->side,
                'accepted_at' => $acc->accepted_at?->toIso8601String(),
            ];
        });

        $acceptedByComplainant = $prop->acceptances->where('side', 'complainant')->isNotEmpty();
        $acceptedByRespondent = $prop->acceptances->where('side', 'respondent')->isNotEmpty();

        return [
            'id' => $prop->id,
            'tribunal_mediation_id' => $prop->tribunal_mediation_id,
            'proposed_by' => $prop->proposed_by,
            'proposer_name' => $proposerName,
            'proposed_by_side' => $prop->proposed_by_side,
            'parent_proposal_id' => $prop->parent_proposal_id,
            'version_number' => $prop->version_number,
            'terms' => $prop->terms,
            'status' => $prop->status?->value ?? (string) $prop->status,
            'is_mine' => $isMine,
            'acceptances' => $acceptances,
            'accepted_by_complainant' => $acceptedByComplainant,
            'accepted_by_respondent' => $acceptedByRespondent,
            'created_at' => $prop->created_at?->toIso8601String(),
            'updated_at' => $prop->updated_at?->toIso8601String(),
        ];
    }
}
