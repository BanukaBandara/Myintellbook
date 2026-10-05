<?php

namespace App\Http\Resources\Tribunal;

use App\Models\TribunalRepresentationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TribunalRepresentationRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var TribunalRepresentationRequest $req */
        $req = $this->resource;

        $client = $req->client;
        $clientName = $client?->profile?->first_name
            ? "{$client->profile->first_name} {$client->profile->last_name}"
            : ($client?->email ?? 'Client');

        $rep = $req->representative;
        $repName = $rep?->profile?->first_name
            ? "{$rep->profile->first_name} {$rep->profile->last_name}"
            : ($rep?->email ?? 'Attorney');

        $case = $req->tribunalCase;

        return [
            'id' => $req->id,
            'tribunal_case_id' => $req->tribunal_case_id,
            'case_number' => $case?->case_number,
            'case_title' => $case?->title,
            'case_category' => $case?->category,
            'case_status' => $case?->status?->value ?? (string) $case?->status,
            'requested_by' => $req->requested_by,
            'client' => [
                'id' => $client?->id,
                'name' => $clientName,
                'email' => $client?->email,
            ],
            'representative' => [
                'id' => $rep?->id,
                'name' => $repName,
                'email' => $rep?->email,
                'profession_label' => 'Attorney-at-Law',
                'masked_enrollment_number' => $rep?->latestProfessionalVerification?->maskedEnrollmentNumber(),
            ],
            'side' => $req->side instanceof \App\Enums\TribunalPartyRole ? $req->side->value : (string) $req->side,
            'status' => $req->status instanceof \App\Enums\TribunalRepresentationRequestStatus ? $req->status->value : (string) $req->status,
            'status_label' => $req->status instanceof \App\Enums\TribunalRepresentationRequestStatus ? $req->status->label() : (string) $req->status,
            'message' => $req->message,
            'requested_at' => $req->requested_at?->toIso8601String(),
            'responded_at' => $req->responded_at?->toIso8601String(),
            'decline_reason' => $req->decline_reason,
            'created_at' => $req->created_at?->toIso8601String(),
        ];
    }
}
