<?php

namespace App\Http\Resources\Professional;

use App\Enums\ProfessionalVerificationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfessionalVerificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();
        $isOwner = $currentUser && $currentUser->id === $this->user_id;
        $isAdmin = $currentUser && $currentUser->isAdmin();

        $data = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'profession_type' => $this->profession_type instanceof \App\Enums\ProfessionalType
                ? $this->profession_type->value
                : (string) $this->profession_type,
            'profession_label' => $this->profession_type instanceof \App\Enums\ProfessionalType
                ? $this->profession_type->label()
                : ucwords(str_replace('_', ' ', (string) $this->profession_type)),
            'verification_status' => $this->verification_status instanceof ProfessionalVerificationStatus
                ? $this->verification_status->value
                : (string) $this->verification_status,
            'is_verified' => $this->isVerified(),
            'badge_title' => $this->isVerified()
                ? 'Verified ' . ($this->profession_type instanceof \App\Enums\ProfessionalType ? $this->profession_type->label() : 'Legal Professional')
                : null,
            'issuing_authority' => $this->issuing_authority,
            'years_of_experience' => $this->years_of_experience,
            'masked_registration_number' => $this->maskedRegistrationNumber(),
            'masked_enrollment_number' => $this->maskedEnrollmentNumber(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_expired' => $this->isExpired(),
        ];

        // Include user details if loaded
        if ($this->relationLoaded('user') && $this->user) {
            $data['user'] = [
                'id' => $this->user->id,
                'email' => $this->user->email,
                'name' => $this->user->profile
                    ? trim(($this->user->profile->first_name ?? '') . ' ' . ($this->user->profile->last_name ?? ''))
                    : $this->user->email,
            ];
        }

        // Include adjudicator profile if loaded
        if ($this->relationLoaded('adjudicatorProfile') && $this->adjudicatorProfile) {
            $data['adjudicator_profile'] = [
                'id' => $this->adjudicatorProfile->id,
                'status' => $this->adjudicatorProfile->status?->value ?? $this->adjudicatorProfile->status,
                'qualification_status' => $this->adjudicatorProfile->qualification_status?->value ?? $this->adjudicatorProfile->qualification_status,
                'qualification_score' => $this->adjudicatorProfile->qualification_score,
                'qualified_at' => $this->adjudicatorProfile->qualified_at?->toIso8601String(),
                'available' => (bool) $this->adjudicatorProfile->available,
                'is_eligible' => $this->adjudicatorProfile->isEligibleAdjudicator(),
            ];
        }

        // Only owner or admin can see reasons and document availability flags
        if ($isOwner || $isAdmin) {
            $data['rejection_reason'] = $this->rejection_reason;
            $data['suspension_reason'] = $this->suspension_reason;
            $data['reviewed_at'] = $this->reviewed_at?->toIso8601String();
            $data['has_qualification_document'] = !empty($this->qualification_document_path);
            $data['has_identity_document'] = !empty($this->identity_document_path);
            $data['has_additional_document'] = !empty($this->additional_document_path);
        }

        // Full unmasked registration/enrollment numbers only for admin or owner
        if ($isAdmin) {
            $data['registration_number'] = $this->registration_number;
            $data['enrollment_number'] = $this->enrollment_number;
            $data['verified_by'] = $this->verified_by;
        }

        // Include audit events if loaded (e.g. on admin detail view)
        if ($isAdmin && $this->relationLoaded('events')) {
            $data['events'] = $this->events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'actor_id' => $event->actor_id,
                    'metadata' => $event->metadata,
                    'created_at' => $event->created_at?->toIso8601String(),
                ];
            });
        }

        return $data;
    }
}
