<?php

namespace App\Services\InternalTribunal;

use App\Enums\InternalPenaltyType;
use App\Models\InternalPenalty;
use App\Models\InternalReport;
use App\Models\User;
use App\Notifications\InternalTribunal\ReportedUserSanitizedActionNotification;
use Illuminate\Support\Facades\DB;

class InternalPenaltyService
{
    public function __construct(
        protected InternalReportAuditService $auditService
    ) {
    }

    public function applyPenalty(
        InternalReport $report,
        string $actionType,
        string $reason,
        ?string $notes,
        User $admin
    ): InternalPenalty {
        return DB::transaction(function () use ($report, $actionType, $reason, $notes, $admin) {
            $penalty = InternalPenalty::create([
                'internal_report_id' => $report->id,
                'user_id' => $report->reported_user_id,
                'action_type' => $actionType,
                'reason' => $reason,
                'notes' => $notes,
                'applied_by' => $admin->id,
                'applied_at' => now(),
            ]);

            // Create audit log
            $this->auditService->log(
                $report,
                "Penalty Applied: {$actionType}",
                $admin,
                [
                    'penalty_id' => $penalty->id,
                    'action_type' => $actionType,
                    'reason' => $reason,
                ]
            );

            // Send sanitized notification to reported user (never revealing reporter identity)
            $reportedUser = $report->reportedUser;
            if ($reportedUser) {
                $reportedUser->notify(new ReportedUserSanitizedActionNotification($actionType, $reason));
            }

            return $penalty;
        });
    }

    public function reversePenalty(
        InternalPenalty $penalty,
        string $reversalReason,
        User $admin
    ): InternalPenalty {
        return DB::transaction(function () use ($penalty, $reversalReason, $admin) {
            $penalty->update([
                'reversed_at' => now(),
                'reversed_by' => $admin->id,
                'reversal_reason' => $reversalReason,
            ]);

            $report = $penalty->report;
            if ($report) {
                $this->auditService->log(
                    $report,
                    "Penalty Reversed: {$penalty->action_type?->value}",
                    $admin,
                    [
                        'penalty_id' => $penalty->id,
                        'reversal_reason' => $reversalReason,
                    ]
                );
            }

            return $penalty;
        });
    }
}
