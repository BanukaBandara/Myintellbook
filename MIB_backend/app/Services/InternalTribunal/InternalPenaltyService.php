<?php

namespace App\Services\InternalTribunal;

use App\Enums\InternalPenaltyType;
use App\Enums\InternalReportStatus;
use App\Enums\RestrictedFeature;
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
        ?int $durationDays = null,
        ?string $penaltyValue = null,
        ?string $restrictionDurationType = null
    ): InternalPenalty {
        return DB::transaction(function () use (
            $report,
            $actionType,
            $reason,
            $notes,
            $admin,
            $durationDays,
            $penaltyValue,
            $restrictionDurationType
        ) {
            $statusVal = $report->status instanceof \BackedEnum ? $report->status->value : (string) $report->status;
            if (in_array($actionType, [
                InternalPenaltyType::FeatureRestriction->value,
                InternalPenaltyType::TemporarySuspension->value,
                InternalPenaltyType::PermanentSuspension->value,
            ]) && $statusVal !== InternalReportStatus::Valid->value) {
                throw new \DomainException("Penalties and feature restrictions can only be applied to reports with 'Valid' status. Current status is '{$statusVal}'.");
            }

            $reportedUser = $report->reportedUser;

            // Immunity checks for Admins & Jury Panels
            if (in_array($actionType, [
                InternalPenaltyType::TemporarySuspension->value,
                InternalPenaltyType::PermanentSuspension->value,
            ])) {
                if ($reportedUser?->isAdmin() || $reportedUser?->isJuryPanelAccount()) {
                    throw new \DomainException("Administrators and Jury Panel accounts cannot be suspended.");
                }
            }

            if ($actionType === InternalPenaltyType::FeatureRestriction->value) {
                if ($reportedUser?->isAdmin() || $reportedUser?->isJuryPanelAccount()) {
                    throw new \DomainException("Administrators and Jury Panel accounts cannot receive feature restrictions.");
                }
            }

            $days = $durationDays ?? (request()->filled('duration_days') ? request()->integer('duration_days') : null);
            $featureVal = $penaltyValue ?? (request()->filled('penalty_value') ? request()->string('penalty_value')->value() : null);
            $durationType = $restrictionDurationType ?? (request()->filled('restriction_duration_type') ? request()->string('restriction_duration_type')->value() : null);

            $startsAt = null;
            $endsAt = null;

            if ($actionType === InternalPenaltyType::TemporarySuspension->value) {
                $startsAt = now();
                $endsAt = now()->addDays($days ?: 7);
            } elseif ($actionType === InternalPenaltyType::PermanentSuspension->value) {
                $startsAt = now();
                $endsAt = null;
            } elseif ($actionType === InternalPenaltyType::FeatureRestriction->value) {
                if (!$featureVal) {
                    throw new \DomainException("A feature key must be specified for Feature Restriction.");
                }

                // Verify valid feature key
                if (!in_array($featureVal, array_map(fn ($f) => $f->value, RestrictedFeature::cases()))) {
                    throw new \DomainException("Invalid feature key '{$featureVal}' specified.");
                }

                // Check for duplicate active restriction
                if ($reportedUser && app(AccountFeatureRestrictionService::class)->isRestricted($reportedUser, $featureVal)) {
                    throw new \DomainException("An active restriction for '{$featureVal}' already exists for this user.");
                }

                if ($durationType === 'permanent') {
                    $startsAt = now();
                    $endsAt = null;
                } elseif ($durationType === 'temporary') {
                    if (!$days) {
                        throw new \DomainException("Duration days is required for temporary feature restriction.");
                    }
                    $startsAt = now();
                    $endsAt = now()->addDays($days);
                } else {
                    throw new \DomainException("A duration type ('temporary' or 'permanent') is required for feature restrictions.");
                }
            }

            $penalty = new InternalPenalty();
            $penalty->internal_report_id = $report->id;
            $penalty->user_id = $report->reported_user_id;
            $penalty->action_type = $actionType;
            $penalty->penalty_value = ($actionType === InternalPenaltyType::FeatureRestriction->value) ? $featureVal : null;
            $penalty->reason = $reason;
            $penalty->notes = $notes;
            $penalty->applied_by = $admin->id;
            $penalty->applied_at = now();
            $penalty->starts_at = $startsAt;
            $penalty->ends_at = $endsAt;
            $penalty->save();

            // Revoke all existing api_tokens ONLY for suspended user (NOT for feature restriction)
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
            if ($penalty->penalty_value) {
                $auditDetails['penalty_value'] = $penalty->penalty_value;
            }
            if ($startsAt) {
                $auditDetails['starts_at'] = $startsAt->toIso8601String();
            }
            if ($endsAt) {
                $auditDetails['ends_at'] = $endsAt->toIso8601String();
            }

            $this->auditService->log(
                $report,
                "Penalty Applied: {$actionType}" . ($penalty->penalty_value ? " ({$penalty->penalty_value})" : ""),
                $admin,
                $auditDetails
            );

            // Send sanitized notification to reported user (never revealing reporter identity)
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

                $actionString = $penalty->penalty_value
                    ? "Penalty Reversed: {$actionVal} ({$penalty->penalty_value})"
                    : "Penalty Reversed: {$actionVal}";

                $auditDetails = [
                    'penalty_id' => $penalty->id,
                    'reversal_reason' => $reversalReason,
                ];
                if ($penalty->penalty_value) {
                    $auditDetails['penalty_value'] = $penalty->penalty_value;
                }

                $this->auditService->log(
                    $report,
                    $actionString,
                    $admin,
                    $auditDetails
                );
            }

            return $penalty;
        });
    }
}
