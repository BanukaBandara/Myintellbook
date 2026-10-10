<?php

namespace App\Services\InternalTribunal;

use App\Enums\InternalPenaltyType;
use App\Models\InternalPenalty;
use App\Models\User;

class AccountProfessionalDisciplineService
{
    /**
     * Check if a user has an active Professional Eligibility Suspension.
     */
    public function hasActiveEligibilitySuspension(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;
        $userModel = $user instanceof User ? $user : User::find($userId);

        if (!$userModel) {
            return false;
        }

        // Immunity for Admins and Jury Panels
        if ($userModel->isAdmin() || $userModel->isJuryPanelAccount()) {
            return false;
        }

        return $this->activeEligibilitySuspensionQuery($userId)->exists();
    }

    /**
     * Get the active Professional Eligibility Suspension penalty record, if one exists.
     */
    public function getActiveEligibilitySuspension(User|int $user): ?InternalPenalty
    {
        $userId = $user instanceof User ? $user->id : $user;
        $userModel = $user instanceof User ? $user : User::find($userId);

        if (!$userModel || $userModel->isAdmin() || $userModel->isJuryPanelAccount()) {
            return null;
        }

        return $this->activeEligibilitySuspensionQuery($userId)->latest('id')->first();
    }

    /**
     * Get user IDs currently under an active Professional Eligibility Suspension.
     *
     * @return array<int>
     */
    public function getSuspendedUserIds(): array
    {
        return InternalPenalty::query()
            ->where('action_type', InternalPenaltyType::ProfessionalEligibilitySuspension->value)
            ->whereNull('reversed_at')
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Base query for active eligibility suspension.
     */
    protected function activeEligibilitySuspensionQuery(int $userId)
    {
        return InternalPenalty::query()
            ->where('user_id', $userId)
            ->where('action_type', InternalPenaltyType::ProfessionalEligibilitySuspension->value)
            ->whereNull('reversed_at')
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            });
    }
}
