<?php

namespace App\Http\Resources\Tribunal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TribunalEvidenceChallengeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->challenger?->profile;
        $name = $profile?->full_name 
            ?: trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''));

        return [
            'id' => $this->id,
            'tribunal_evidence_id' => $this->tribunal_evidence_id,
            'challenged_by' => [
                'id' => $this->challenged_by,
                'name' => $name ?: ('User #' . $this->challenged_by),
            ],
            'reason' => $this->reason,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'reviewed_at' => $this->reviewed_at,
            'created_at' => $this->created_at,
        ];
    }
}
