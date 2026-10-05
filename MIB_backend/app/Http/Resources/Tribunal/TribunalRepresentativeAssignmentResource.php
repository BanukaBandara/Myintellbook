<?php

namespace App\Http\Resources\Tribunal;

use App\Models\TribunalRepresentativeAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TribunalRepresentativeAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var TribunalRepresentativeAssignment $assignment */
        $assignment = $this->resource;

        $client = $assignment->client;
        $clientName = $client?->profile?->first_name
            ? "{$client->profile->first_name} {$client->profile->last_name}"
            : ($client?->email ?? 'Client');

        $rep = $assignment->representative;
        $repName = $rep?->profile?->first_name
            ? "{$rep->profile->first_name} {$rep->profile->last_name}"
            : ($rep?->email ?? 'Attorney');

        $case = $assignment->tribunalCase;

        return [
            'id' => $assignment->id,
            'tribunal_case_id' => $assignment->tribunal_case_id,
            'case_number' => $case?->case_number,
            'case_title' => $case?->title,
            'case_category' => $case?->category,
            'case_status' => $case?->status?->value ?? (string) $case?->status,
            'side' => $assignment->side instanceof \App\Enums\TribunalPartyRole ? $assignment->side->value : (string) $assignment->side,
            'status' => $assignment->status instanceof \App\Enums\TribunalRepresentativeAssignmentStatus ? $assignment->status->value : (string) $assignment->status,
            'status_label' => $assignment->status instanceof \App\Enums\TribunalRepresentativeAssignmentStatus ? $assignment->status->label() : (string) $assignment->status,
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
            'accepted_at' => $assignment->accepted_at?->toIso8601String(),
            'ended_at' => $assignment->ended_at?->toIso8601String(),
            'end_reason' => $assignment->end_reason,
        ];
    }
}
