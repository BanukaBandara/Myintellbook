<?php

namespace App\Http\Resources\Tribunal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TribunalEvidenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->uploader?->profile;
        $name = $profile?->full_name 
            ?: trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''));

        $currentUserId = auth()->id();
        $isParty = $this->case ? $this->case->isParticipant($currentUserId) : false;
        $isUploader = $this->uploaded_by === $currentUserId;
        $hasPendingChallenge = $this->relationLoaded('challenges')
            ? $this->challenges->where('challenged_by', $currentUserId)->where('status.value', 'pending')->isNotEmpty()
            : false;

        return [
            'id' => $this->id,
            'tribunal_case_id' => $this->tribunal_case_id,
            'evidence_number' => $this->evidence_number,
            'type' => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'sha256_hash' => $this->sha256_hash,
            'external_url' => $this->external_url,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'submitted_at' => $this->submitted_at,
            'uploaded_by' => [
                'id' => $this->uploaded_by,
                'name' => $name ?: ('User #' . $this->uploaded_by),
                'is_current_user' => $isUploader,
            ],
            'has_file' => !empty($this->file_path),
            'download_url' => !empty($this->file_path)
                ? "/api/tribunal/cases/{$this->tribunal_case_id}/evidence/{$this->id}/download"
                : null,
            'challenges' => TribunalEvidenceChallengeResource::collection($this->whenLoaded('challenges')),
            'can_challenge' => $isParty && !$isUploader && !$hasPendingChallenge,
        ];
    }
}
