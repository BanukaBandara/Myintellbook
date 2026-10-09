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
        User $admin,
        ?int $durationDays = null
    ): InternalPenalty {
        return DB::transaction(function () use ($report, $actionType, $reason, $notes, $admin, $durationDays) {
            $days = $durationDays ?? (request()->filled('duration_days') ? request()->integer('duration_days') : null);
            $startsAt = null;
            $endsAt = null;

            if ($actionType === InternalPenaltyType::TemporarySuspension->value) {
                $startsAt = now();
                $endsAt = now()->addDays($days ?: 7);
            } elseif ($actionType === InternalPenaltyType::PermanentSuspension->value) {
                $startsAt = now();
                $endsAt = null;
            }

            $penalty = new InternalPenalty();
            $penalty->internal_report_id = $report->id;
            $penalty->user_id = $report->reported_user_id;
            $penalty->action_type = $actionType;
            $penalty->reason = $reason;
            $penalty->notes = $notes;
            $penalty->applied_by = $admin->id;
            $penalty->applied_at = now();
            $penalty->starts_at = $startsAt;
            $penalty->ends_at = $endsAt;
            $penalty->save();

            // Revoke all existing api_tokens for suspended user inside transaction
            if (in_array($actionType, [
                InternalPenaltyType::TemporarySuspension->value,
                InternalPenaltyType::PermanentSuspension->value,
            ])) {
                \App\Models\ApiToken::where('user_id', $report->reported_user_id)->delete();
            }

            // Create audit log
            $auditDetails = [
                'penalty_id' => $penalty->id,
                'action_type' => $actionType,
                'reason' => $reason,
            ];
            if ($startsAt) {
                $auditDetails['starts_at'] = $startsAt->toIso8601String();
            }
            if ($endsAt) {
                $auditDetails['ends_at'] = $endsAt->toIso8601String();
            }

            $this->auditService->log(
                $report,
                "Penalty Applied: {$actionType}",
                $admin,
                $auditDetails
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
                $actionVal = $penalty->action_type instanceof InternalPenaltyType
                    ? $penalty->action_type->value
                    : (string) $penalty->action_type;

                $this->auditService->log(
                    $report,
                    "Penalty Reversed: {$actionVal}",
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
