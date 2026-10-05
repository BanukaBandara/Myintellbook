<?php

namespace App\Observers;

use App\Models\TribunalReport;
use App\Models\User;
use App\Services\HipScoreCalculator;

class TribunalReportObserver
{
    public function saved(TribunalReport $report): void
    {
        $this->recalculate($report->user_id);
    }

    public function deleted(TribunalReport $report): void
    {
        $this->recalculate($report->user_id);
    }

    private function recalculate(?int $userId): void
    {
        $user = $userId === null ? null : User::find($userId);
        if ($user !== null) {
            HipScoreCalculator::calculate($user);
        }
    }
}
