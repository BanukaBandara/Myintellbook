<?php

namespace App\Http\Resources\Tribunal;

use App\Models\TribunalSettlementAgreement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TribunalSettlementAgreementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var TribunalSettlementAgreement $agreement */
        $agreement = $this->resource;

        return [
            'id' => $agreement->id,
            'tribunal_case_id' => $agreement->tribunal_case_id,
            'tribunal_mediation_id' => $agreement->tribunal_mediation_id,
            'settlement_proposal_id' => $agreement->settlement_proposal_id,
            'agreement_number' => $agreement->agreement_number,
            'terms_snapshot' => $agreement->terms_snapshot,
            'complainant_accepted_at' => $agreement->complainant_accepted_at?->toIso8601String(),
            'respondent_accepted_at' => $agreement->respondent_accepted_at?->toIso8601String(),
            'finalized_at' => $agreement->finalized_at?->toIso8601String(),
            'created_at' => $agreement->created_at?->toIso8601String(),
        ];
    }
}
