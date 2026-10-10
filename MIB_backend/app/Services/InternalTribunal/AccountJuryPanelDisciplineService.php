<?php

namespace App\Services\InternalTribunal;

use App\Enums\InternalPenaltyType;
use App\Models\InternalPenalty;
use App\Models\TribunalJuryPanel;
use App\Models\User;

class AccountJuryPanelDisciplineService
{
    /**
     * Resolve target login user ID from User, TribunalJuryPanel, or integer ID.
     */
    protected function resolveUserId(User|TribunalJuryPanel|int $panelOrUser): ?int
    {
        if ($panelOrUser instanceof TribunalJuryPanel) {
            return $panelOrUser->login_user_id;
        }

        if ($panelOrUser instanceof User) {
            if (!$panelOrUser->isJuryPanelAccount()) {
                return null;
            }
            return $panelOrUser->id;
        }

        if (is_int($panelOrUser)) {
            // Check if it's the login_user_id of a Jury Panel
            if (TribunalJuryPanel::where('login_user_id', $panelOrUser)->exists()) {
                return $panelOrUser;
            }

            // Or if it's the ID of a TribunalJuryPanel record
            $panel = TribunalJuryPanel::find($panelOrUser);
            if ($panel) {
                return $panel->login_user_id;
            }

            return null;
        }

        return null;
    }

    /**
     * Check if a Jury Panel or its login user has an active Jury Panel Deactivation penalty.
     */
    public function hasActiveDeactivation(User|TribunalJuryPanel|int $panelOrUser): bool
    {
        $userId = $this->resolveUserId($panelOrUser);
        if (!$userId) {
            return false;
        }

        return $this->activeDeactivationQuery($userId)->exists();
    }

    /**
     * Get the active Jury Panel Deactivation penalty record if one exists.
     */
    public function getActiveDeactivation(User|TribunalJuryPanel|int $panelOrUser): ?InternalPenalty
    {
        $userId = $this->resolveUserId($panelOrUser);
        if (!$userId) {
            return null;
        }

        return $this->activeDeactivationQuery($userId)->latest('id')->first();
    }

    /**
     * Get list of tribunal_jury_panels IDs currently under an active deactivation penalty.
     *
     * @return array<int>
     */
    public function getDeactivatedPanelIds(): array
    {
        $userIds = $this->getDeactivatedUserIds();
        if (empty($userIds)) {
            return [];
        }

        return TribunalJuryPanel::whereIn('login_user_id', $userIds)
            ->pluck('id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get list of user IDs currently under an active Jury Panel Deactivation penalty.
     *
     * @return array<int>
     */
    public function getDeactivatedUserIds(): array
    {
        return InternalPenalty::query()
            ->where('action_type', InternalPenaltyType::JuryPanelDeactivation->value)
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
     * Base query for active Jury Panel Deactivation penalties for a given user ID.
     */
    protected function activeDeactivationQuery(int $userId)
    {
        return InternalPenalty::query()
            ->where('user_id', $userId)
            ->where('action_type', InternalPenaltyType::JuryPanelDeactivation->value)
            ->whereNull('reversed_at')
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            });
    }
}
