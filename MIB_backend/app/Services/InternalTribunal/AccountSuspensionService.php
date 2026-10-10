<?php

namespace App\Services\InternalTribunal;

use App\Enums\InternalPenaltyType;
use App\Models\InternalPenalty;
use App\Models\User;

class AccountSuspensionService
{
    /**
     * Retrieve the currently active suspension record for a user, if any.
     * Globally exempts Super Admin accounts and Jury Panel accounts.
     */
    public function getActiveSuspension(User|int $user): ?InternalPenalty
    {
        $userModel = $user instanceof User ? $user : User::find($user);

        if (!$userModel) {
            return null;
        }

        // Defense-in-depth: Super Admins and Jury Panels are globally exempt from user account suspensions
        if ($userModel->isAdmin() || $userModel->isJuryPanelAccount()) {
            return null;
        }

        return InternalPenalty::where('user_id', $userModel->id)
            ->whereIn('action_type', [
                InternalPenaltyType::TemporarySuspension->value,
                InternalPenaltyType::PermanentSuspension->value,
            ])
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
     * Check if the user is currently suspended.
     */
    public function isUserSuspended(User|int $user): bool
    {
        return $this->getActiveSuspension($user) !== null;
    }

    /**
     * Format a safe, sanitized client payload for HTTP 403 responses.
     * Contains zero private reasoning, zero reporter identity, and zero internal notes.
     */
    public function formatSuspensionPayload(InternalPenalty $suspension): array
    {
        $actionValue = $suspension->action_type instanceof InternalPenaltyType
            ? $suspension->action_type->value
            : (string) $suspension->action_type;

        $isTemporary = $actionValue === InternalPenaltyType::TemporarySuspension->value;
        $endsAt = $suspension->ends_at ? \Carbon\Carbon::parse($suspension->ends_at) : null;

        return [
            'code' => 403,
            'status' => false,
            'message' => $isTemporary
                ? sprintf('Your account is temporarily suspended until %s.', $endsAt ? $endsAt->toIso8601String() : '')
                : 'Your account is permanently suspended.',
            'suspended' => true,
            'suspension_type' => $isTemporary ? 'temporary' : 'permanent',
            'ends_at' => $isTemporary ? $endsAt?->toIso8601String() : null,
        ];
    }
}
