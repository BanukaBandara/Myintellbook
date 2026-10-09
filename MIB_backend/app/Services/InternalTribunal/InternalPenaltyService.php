<?php

namespace App\Services\InternalTribunal;

use App\Enums\InternalPenaltyType;
use App\Enums\InternalReportStatus;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\RestrictedFeature;
use App\Enums\TribunalJuryAssignmentStatus;
use App\Models\InternalPenalty;
use App\Models\InternalReport;
use App\Models\TribunalJuryAssignment;
use App\Models\User;
use App\Notifications\InternalTribunal\ReportedJuryPanelDeactivationNotification;
use App\Notifications\InternalTribunal\ReportedUserProfessionalDisciplineNotification;
use App\Notifications\InternalTribunal\ReportedUserSanitizedActionNotification;
use App\Services\InternalTribunal\AccountJuryPanelDisciplineService;
use App\Services\Professional\ProfessionalVerificationService;
use App\Services\Tribunal\TribunalRepresentationService;
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
                InternalPenaltyType::VerificationRevoked->value,
                InternalPenaltyType::ProfessionalEligibilitySuspension->value,
                InternalPenaltyType::JuryPanelDeactivation->value,
            ]) && $statusVal !== InternalReportStatus::Valid->value) {
                throw new \DomainException("Penalties, feature restrictions, and professional discipline can only be applied to reports with 'Valid' status. Current status is '{$statusVal}'.");
            }

            $reportedUser = $report->reportedUser;

            // Immunity checks for Admins & Jury Panels
            if (in_array($actionType, [
                InternalPenaltyType::TemporarySuspension->value,
                InternalPenaltyType::PermanentSuspension->value,
                InternalPenaltyType::FeatureRestriction->value,
                InternalPenaltyType::VerificationRevoked->value,
                InternalPenaltyType::ProfessionalEligibilitySuspension->value,
            ])) {
                if ($reportedUser?->isAdmin() || $reportedUser?->isJuryPanelAccount()) {
                    throw new \DomainException("Administrators and Jury Panel accounts are immune from account suspension, feature restrictions, and professional discipline.");
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
            } elseif ($actionType === InternalPenaltyType::VerificationRevoked->value) {
                $verification = $reportedUser?->latestProfessionalVerification;
                if (!$verification) {
                    throw new \DomainException("The reported user does not have a professional verification profile.");
                }

                $verificationStatus = $verification->verification_status instanceof \BackedEnum
                    ? $verification->verification_status->value
                    : (string) $verification->verification_status;

                if ($verificationStatus === ProfessionalVerificationStatus::Suspended->value) {
                    throw new \DomainException("This user's professional verification is already suspended or revoked.");
                }

                $startsAt = now();
                $endsAt = null;
            } elseif ($actionType === InternalPenaltyType::ProfessionalEligibilitySuspension->value) {
                $verification = $reportedUser?->latestProfessionalVerification;
                if (!$verification) {
                    throw new \DomainException("The reported user does not have a professional verification profile.");
                }

                if (app(AccountProfessionalDisciplineService::class)->hasActiveEligibilitySuspension($reportedUser)) {
                    throw new \DomainException("An active professional eligibility suspension already exists for this user.");
                }

                if ($durationType === 'permanent') {
                    $startsAt = now();
                    $endsAt = null;
                } elseif ($durationType === 'temporary') {
                    if (!$days) {
                        throw new \DomainException("Duration days is required for temporary professional eligibility suspension.");
                    }
                    $startsAt = now();
                    $endsAt = now()->addDays($days);
                } else {
                    throw new \DomainException("A duration type ('temporary' or 'permanent') is required for professional eligibility suspension.");
                }
            } elseif ($actionType === InternalPenaltyType::JuryPanelDeactivation->value) {
                if ($reportedUser?->isAdmin()) {
                    throw new \DomainException("Super Administrators cannot receive Jury Panel deactivation.");
                }

                if (!$reportedUser?->isJuryPanelAccount()) {
                    throw new \DomainException("Jury Panel Deactivation can only be applied to institutional Jury Panel accounts.");
                }

                if (app(AccountJuryPanelDisciplineService::class)->hasActiveDeactivation($reportedUser)) {
                    throw new \DomainException("An active Jury Panel deactivation already exists for this panel.");
                }

                if ($durationType === 'permanent') {
                    $startsAt = now();
                    $endsAt = null;
                } elseif ($durationType === 'temporary') {
                    if (!$days) {
                        throw new \DomainException("Duration days is required for temporary Jury Panel deactivation.");
                    }
                    $startsAt = now();
                    $endsAt = now()->addDays($days);
                } else {
                    throw new \DomainException("A duration type ('temporary' or 'permanent') is required for Jury Panel deactivation.");
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

            // Revoke all existing api_tokens ONLY for suspended user (NOT for feature restriction or professional discipline)
            if (in_array($actionType, [
                InternalPenaltyType::TemporarySuspension->value,
                InternalPenaltyType::PermanentSuspension->value,
            ])) {
                \App\Models\ApiToken::where('user_id', $report->reported_user_id)->delete();
            }

            // Execute specific actions for Verification Revoked
            if ($actionType === InternalPenaltyType::VerificationRevoked->value) {
                $verification = $reportedUser->latestProfessionalVerification;
                $sanitizedPublicReason = 'Professional verification suspended following an administrative review.';

                // Re-use existing ProfessionalVerificationService::suspend (handles verification, legacy profile sync, and its own notification)
                app(ProfessionalVerificationService::class)->suspend($verification, $admin, $sanitizedPublicReason);

                // Terminate active representations, deactivate private conversations, and cancel pending requests
                app(TribunalRepresentationService::class)->terminateAssignmentsForRevokedRepresentative(
                    $reportedUser->id,
                    $admin,
                    'Legal professional verification revoked following administrative review.'
                );

                // Safely recuse any historical legacy accepted adjudicator assignments without invoking legacy reassignment
                TribunalJuryAssignment::where('juror_id', $reportedUser->id)
                    ->where('status', TribunalJuryAssignmentStatus::Accepted)
                    ->update([
                        'status' => TribunalJuryAssignmentStatus::Recused,
                        'recusal_reason' => 'Professional credentials revoked following administrative review.',
                        'responded_at' => now(),
                    ]);
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

            // User notification dispatch via DB::afterCommit:
            // Verification Revoked: user receives single notification from ProfessionalVerificationService::suspend(). We DO NOT send a duplicate.
            // Professional Eligibility Suspension: send single sanitized ReportedUserProfessionalDisciplineNotification.
            // Jury Panel Deactivation: send single sanitized ReportedJuryPanelDeactivationNotification.
            // Other penalties: send sanitized ReportedUserSanitizedActionNotification.
            if ($reportedUser) {
                if ($actionType === InternalPenaltyType::JuryPanelDeactivation->value) {
                    $isTemp = ($durationType === 'temporary');
                    DB::afterCommit(function () use ($reportedUser, $isTemp, $endsAt) {
                        $reportedUser->notify(new ReportedJuryPanelDeactivationNotification($isTemp, $endsAt));
                    });
                } elseif ($actionType === InternalPenaltyType::ProfessionalEligibilitySuspension->value) {
                    $isTemp = ($durationType === 'temporary');
                    DB::afterCommit(function () use ($reportedUser, $actionType, $isTemp, $endsAt) {
                        $reportedUser->notify(new ReportedUserProfessionalDisciplineNotification($actionType, $isTemp, $endsAt));
                    });
                } elseif ($actionType !== InternalPenaltyType::VerificationRevoked->value) {
                    DB::afterCommit(function () use ($reportedUser, $actionType, $reason) {
                        $reportedUser->notify(new ReportedUserSanitizedActionNotification($actionType, $reason));
                    });
                }
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

            // Conservative strategy for Verification Revoked:
            // ProfessionalVerification remains Suspended. Adjudicator/representative eligibility is NOT restored.
            // Super Admin must explicitly re-review and re-approve via AdminProfessionalVerificationController::approve().

            // For Professional Eligibility Suspension:
            // Dynamic query in AccountProfessionalDisciplineService immediately ceases matching reversed penalty.
            // Zero profile mutations were made, so authentic underlying state immediately resumes.

            return $penalty;
        });
    }
}
