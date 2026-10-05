<?php

namespace App\Observers;

use App\Models\ProfessionalVerification;
use App\Models\User;
use App\Services\HipScoreCalculator;

class ProfessionalVerificationScoreObserver
{
    public function saved(ProfessionalVerification $verification): void
    {
        $this->recalculate($verification->user_id);
    }

    public function deleted(ProfessionalVerification $verification): void
    {
        $this->recalculate($verification->user_id);
    }

    private function recalculate(?int $userId): void
    {
        $user = $userId === null ? null : User::find($userId);
        if ($user !== null) {
            HipScoreCalculator::calculate($user);
        }
    }
}
