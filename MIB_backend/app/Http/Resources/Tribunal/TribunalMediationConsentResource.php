<?php

namespace App\Http\Resources\Tribunal;

use App\Models\TribunalMediationConsent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TribunalMediationConsentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var TribunalMediationConsent $consent */
        $consent = $this->resource;

        $user = $consent->user;
        $userName = $user?->profile?->first_name
            ? "{$user->profile->first_name} {$user->profile->last_name}"
            : ($user?->email ?? 'User');

        return [
            'id' => $consent->id,
            'tribunal_mediation_id' => $consent->tribunal_mediation_id,
            'user_id' => $consent->user_id,
            'user_name' => $userName,
            'side' => $consent->side,
            'response' => $consent->response,
            'responded_at' => $consent->responded_at?->toIso8601String(),
        ];
    }
}
