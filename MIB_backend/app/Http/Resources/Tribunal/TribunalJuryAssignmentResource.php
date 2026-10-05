<?php

namespace App\Http\Resources\Tribunal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TribunalJuryAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $case = $this->case;

        $complainantParty = $case?->parties?->firstWhere('role.value', 'complainant');
        $complainantProfile = $complainantParty?->user?->profile;
        $complainantName = $complainantProfile?->full_name 
            ?: trim(($complainantProfile?->first_name ?? '') . ' ' . ($complainantProfile?->last_name ?? ''));

        $respondentParty = $case?->parties?->firstWhere('role.value', 'respondent');
        $respondentProfile = $respondentParty?->user?->profile;
        $respondentName = $respondentProfile?->full_name 
            ?: trim(($respondentProfile?->first_name ?? '') . ' ' . ($respondentProfile?->last_name ?? ''));

        return [
            'id' => $this->id,
            'tribunal_case_id' => $this->tribunal_case_id,
            'role' => $this->role instanceof \BackedEnum ? $this->role->value : $this->role,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'assigned_at' => $this->assigned_at,
            'responded_at' => $this->responded_at,
            'case' => $case ? [
                'id' => $case->id,
                'case_number' => $case->case_number,
                'title' => $case->title,
                'category' => $case->category,
                'status' => $case->status instanceof \BackedEnum ? $case->status->value : $case->status,
                'complainant_name' => $complainantName ?: 'Anonymous Complainant',
                'respondent_name' => $respondentName ?: 'Anonymous Respondent',
            ] : null,
        ];
    }
}
