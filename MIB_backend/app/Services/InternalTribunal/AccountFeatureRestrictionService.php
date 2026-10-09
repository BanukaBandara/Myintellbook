<?php

namespace App\Services\InternalTribunal;

use App\Enums\InternalPenaltyType;
use App\Enums\RestrictedFeature;
use App\Models\InternalPenalty;
use App\Models\User;
use Carbon\Carbon;

class AccountFeatureRestrictionService
{
    /**
     * Retrieve the currently active restriction record for a user and feature, if any.
     * Globally exempts Super Admin accounts and Jury Panel accounts.
     */
    public function getActiveRestriction(User|int $user, string|RestrictedFeature $feature): ?InternalPenalty
    {
        $userModel = $user instanceof User ? $user : User::find($user);

        if (!$userModel) {
            return null;
        }

        // Defense-in-depth: Super Admins and Jury Panels are globally exempt from feature restrictions
        if ($userModel->isAdmin() || $userModel->isJuryPanelAccount()) {
            return null;
        }

        $featureKey = $feature instanceof RestrictedFeature ? $feature->value : (string) $feature;

        return InternalPenalty::where('user_id', $userModel->id)
            ->where('action_type', InternalPenaltyType::FeatureRestriction->value)
            ->where('penalty_value', $featureKey)
            ->whereNull('reversed_at')
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->latest('id')
            ->first();
    }

    /**
     * Check if the user is currently restricted from a specific feature.
     */
    public function isRestricted(User|int $user, string|RestrictedFeature $feature): bool
    {
        return $this->getActiveRestriction($user, $feature) !== null;
    }

    /**
     * Format a safe, sanitized client payload for HTTP 403 responses.
     * Contains zero private reasoning, zero reporter identity, and zero internal notes.
     */
    public function formatRestrictionPayload(string|RestrictedFeature $feature, ?InternalPenalty $restriction = null): array
    {
        $featureKey = $feature instanceof RestrictedFeature ? $feature->value : (string) $feature;
        $endsAt = $restriction?->ends_at ? Carbon::parse($restriction->ends_at) : null;

        return [
            'code' => 403,
            'status' => false,
            'message' => $endsAt
                ? sprintf('Access to this feature is temporarily restricted until %s.', $endsAt->toIso8601String())
                : 'Access to this feature is currently restricted.',
            'feature_restricted' => true,
            'feature' => $featureKey,
            'ends_at' => $endsAt?->toIso8601String(),
        ];
    }
}
