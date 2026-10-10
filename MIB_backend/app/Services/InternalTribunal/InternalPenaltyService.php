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
use App\Notifications\InternalTribunal\ReportedUserContentRemovalNotification;
use App\Notifications\InternalTribunal\ReportedUserProfessionalDisciplineNotification;
use App\Notifications\InternalTribunal\ReportedUserSanitizedActionNotification;
use App\Notifications\InternalTribunal\ReportedUserScorePenaltyNotification;
use App\Services\HipScoreCalculator;
use App\Services\InternalTribunal\AccountJuryPanelDisciplineService;
use App\Services\InternalTribunal\ContentRemovalService;
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
                InternalPenaltyType::HipScorePenalty->value,
                InternalPenaltyType::ContentRemoval->value,
            ]) && $statusVal !== InternalReportStatus::Valid->value) {
                throw new \DomainException("Penalties, feature restrictions, professional discipline, score penalties, and content removals can only be applied to reports with 'Valid' status. Current status is '{$statusVal}'.");
            }

            $reportedUser = $report->reportedUser;

            // Immunity checks for Admins & Jury Panels
            if (in_array($actionType, [
                InternalPenaltyType::TemporarySuspension->value,
                InternalPenaltyType::PermanentSuspension->value,
                InternalPenaltyType::FeatureRestriction->value,
                InternalPenaltyType::VerificationRevoked->value,
                InternalPenaltyType::ProfessionalEligibilitySuspension->value,
                InternalPenaltyType::HipScorePenalty->value,
                InternalPenaltyType::ContentRemoval->value,
            ])) {
                if ($reportedUser?->isAdmin() || $reportedUser?->isJuryPanelAccount()) {
                    throw new \DomainException("Administrators and Jury Panel accounts are immune from account suspension, feature restrictions, professional discipline, score penalties, and content removal.");
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
            } elseif ($actionType === InternalPenaltyType::HipScorePenalty->value) {
                if (!$featureVal || !is_numeric($featureVal) || (float) $featureVal < 1.00 || (float) $featureVal > 36825.00) {
                    throw new \DomainException("Penalty points must be a valid number between 1.00 and 36,825.00.");
                }

                $hasActiveScorePenalty = InternalPenalty::where('internal_report_id', $report->id)
                    ->where('user_id', $report->reported_user_id)
                    ->where('action_type', InternalPenaltyType::HipScorePenalty->value)
                    ->whereNull('reversed_at')
                    ->exists();

                if ($hasActiveScorePenalty) {
                    throw new \DomainException("An active HIP / Score Penalty from this report already exists for this user.");
                }

                $startsAt = now();
                $endsAt = null;
            }

            $contentMetadata = null;
            if ($actionType === InternalPenaltyType::ContentRemoval->value) {
                if (!$featureVal) {
                    throw new \DomainException("A target content identifier ('content_type:content_id') must be specified for Content Removal.");
                }

                $contentRemovalService = app(ContentRemovalService::class);
                $parsed = $contentRemovalService->parseIdentifier((string) $featureVal);

                $hasActiveRemoval = InternalPenalty::where('internal_report_id', $report->id)
                    ->where('user_id', $report->reported_user_id)
                    ->where('action_type', InternalPenaltyType::ContentRemoval->value)
                    ->where('penalty_value', (string) $featureVal)
                    ->whereNull('reversed_at')
                    ->exists();

                if ($hasActiveRemoval) {
                    throw new \DomainException("An active Content Removal penalty for this content item already exists for this report.");
                }

                $contentMetadata = $contentRemovalService->remove(
                    $parsed['content_type'],
                    $parsed['content_id'],
                    $reportedUser
                );

                $startsAt = now();
                $endsAt = null;
            }

            $penalty = new InternalPenalty();
            $penalty->internal_report_id = $report->id;
            $penalty->user_id = $report->reported_user_id;
            $penalty->action_type = $actionType;
            $penalty->penalty_value = in_array($actionType, [
                InternalPenaltyType::FeatureRestriction->value,
                InternalPenaltyType::HipScorePenalty->value,
                InternalPenaltyType::ContentRemoval->value,
            ]) ? (string) $featureVal : null;
            $penalty->reason = $reason;
            $penalty->notes = $notes;
            $penalty->applied_by = $admin->id;
            $penalty->applied_at = now();
            $penalty->starts_at = $startsAt;
            $penalty->ends_at = $endsAt;
            $penalty->save();

            // Recalculate HIP score if this is a HIP score penalty
            if ($actionType === InternalPenaltyType::HipScorePenalty->value && $reportedUser) {
                HipScoreCalculator::recalculate($reportedUser);
            }

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
                if ($actionType === InternalPenaltyType::HipScorePenalty->value) {
                    $auditDetails['points_deducted'] = (float) $penalty->penalty_value;
                }
            }
            if ($contentMetadata) {
                $auditDetails['content_type'] = $contentMetadata['content_type'];
                $auditDetails['content_id'] = $contentMetadata['content_id'];
                $auditDetails['original_title'] = $contentMetadata['original_title'];
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
            // HIP / Score Penalty: send single sanitized ReportedUserScorePenaltyNotification.
            // Content Removal: send single sanitized ReportedUserContentRemovalNotification.
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
                } elseif ($actionType === InternalPenaltyType::HipScorePenalty->value) {
                    $pts = (float) $penalty->penalty_value;
                    DB::afterCommit(function () use ($reportedUser, $pts) {
                        $reportedUser->notify(new ReportedUserScorePenaltyNotification($pts));
                    });
                } elseif ($actionType === InternalPenaltyType::ContentRemoval->value) {
                    DB::afterCommit(function () use ($reportedUser) {
                        $reportedUser->notify(new ReportedUserContentRemovalNotification());
                    });
                } elseif (!in_array($actionType, [
                    InternalPenaltyType::VerificationRevoked->value,
                    InternalPenaltyType::ContentRemoval->value,
                ])) {
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
            $actionVal = $penalty->action_type instanceof InternalPenaltyType
                ? $penalty->action_type->value
                : (string) $penalty->action_type;

            $contentRestoreMeta = null;
            if ($actionVal === InternalPenaltyType::ContentRemoval->value) {
                $targetUser = $penalty->user ?? User::find($penalty->user_id);
                if ($targetUser) {
                    $contentRemovalService = app(ContentRemovalService::class);
                    $parsed = $contentRemovalService->parseIdentifier((string) $penalty->penalty_value);
                    $contentRestoreMeta = $contentRemovalService->restore(
                        $parsed['content_type'],
                        $parsed['content_id'],
                        $targetUser
                    );
                }
            }

            $penalty->update([
                'reversed_at' => now(),
                'reversed_by' => $admin->id,
                'reversal_reason' => $reversalReason,
            ]);

            $report = $penalty->report;
            if ($report) {
                $actionString = $penalty->penalty_value
                    ? "Penalty Reversed: {$actionVal} ({$penalty->penalty_value})"
                    : "Penalty Reversed: {$actionVal}";

                $auditDetails = [
                    'penalty_id' => $penalty->id,
                    'reversal_reason' => $reversalReason,
                ];
                if ($penalty->penalty_value) {
                    $auditDetails['penalty_value'] = $penalty->penalty_value;
                    if ($actionVal === InternalPenaltyType::HipScorePenalty->value) {
                        $auditDetails['points_restored'] = (float) $penalty->penalty_value;
                    }
                }
                if ($contentRestoreMeta) {
                    $auditDetails['content_type'] = $contentRestoreMeta['content_type'];
                    $auditDetails['content_id'] = $contentRestoreMeta['content_id'];
                    $auditDetails['restored'] = true;
                }

                $this->auditService->log(
                    $report,
                    $actionString,
                    $admin,
                    $auditDetails
                );
            }

            // Recalculate HIP score if this penalty was a HIP score penalty
            if ($actionVal === InternalPenaltyType::HipScorePenalty->value && $penalty->user) {
                HipScoreCalculator::recalculate($penalty->user);
            }

            return $penalty;
        });
    }
}
