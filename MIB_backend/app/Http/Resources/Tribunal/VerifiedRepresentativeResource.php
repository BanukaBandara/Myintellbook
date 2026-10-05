<?php

namespace App\Http\Resources\Tribunal;

use App\Enums\ProfessionalType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VerifiedRepresentativeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $verification = $user->latestProfessionalVerification;

        $name = $user->profile?->first_name 
            ? "{$user->profile->first_name} {$user->profile->last_name}" 
            : $user->email;

        return [
            'id' => $user->id,
            'name' => $name,
            'email' => $user->email,
            'profession_type' => $verification?->profession_type instanceof ProfessionalType
                ? $verification->profession_type->value
                : (string) $verification?->profession_type,
            'profession_label' => $verification?->profession_type instanceof ProfessionalType
                ? $verification->profession_type->label()
                : 'Attorney-at-Law',
            'masked_registration_number' => $verification?->maskedRegistrationNumber(),
            'masked_enrollment_number' => $verification?->maskedEnrollmentNumber(),
            'years_of_experience' => $verification?->years_of_experience ?? 0,
            'issuing_authority' => $verification?->issuing_authority ?? '',
            'badge_title' => 'Verified Attorney-at-Law',
            'verified_at' => $verification?->verified_at?->toIso8601String(),
            'is_available' => true,
        ];
    }
}
