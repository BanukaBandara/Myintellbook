<?php

namespace App\Observers;

use App\Models\Achievement;
use App\Models\User;
use App\Services\HipScoreCalculator;

class AchievementObserver
{
    public function saved(Achievement $achievement): void
    {
        $this->recalculate($achievement->user_id);
    }

    public function deleted(Achievement $achievement): void
    {
        $this->recalculate($achievement->user_id);
    }

    private function recalculate(?int $userId): void
    {
        $user = $userId === null ? null : User::find($userId);
        if ($user !== null) {
            HipScoreCalculator::calculate($user);
        }
    }
}
