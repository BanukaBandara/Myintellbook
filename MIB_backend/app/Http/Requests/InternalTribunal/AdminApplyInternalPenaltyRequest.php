<?php

namespace App\Http\Requests\InternalTribunal;

use App\Enums\InternalPenaltyType;
use App\Enums\InternalReportStatus;
use App\Enums\RestrictedFeature;
use App\Models\TestamentResourceNote;
use App\Services\InternalTribunal\AccountFeatureRestrictionService;
use App\Services\InternalTribunal\ContentRemovalService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminApplyInternalPenaltyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('action_type') === InternalPenaltyType::ContentRemoval->value) {
            if ($this->filled('content_type') && $this->filled('content_id')) {
                $this->merge([
                    'penalty_value' => "{$this->input('content_type')}:{$this->input('content_id')}",
                ]);
            } elseif ($this->filled('penalty_value') && str_contains((string) $this->input('penalty_value'), ':')) {
                $parts = explode(':', (string) $this->input('penalty_value'), 2);
                $this->merge([
                    'content_type' => $parts[0] ?? null,
                    'content_id' => $parts[1] ?? null,
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'action_type' => [
                'required',
                'string',
                Rule::in(array_map(fn ($p) => $p->value, InternalPenaltyType::cases())),
            ],
            'content_type' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('action_type') === InternalPenaltyType::ContentRemoval->value),
                'string',
                Rule::in([ContentRemovalService::TYPE_TESTAMENT_NOTE]),
            ],
            'content_id' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('action_type') === InternalPenaltyType::ContentRemoval->value),
                'integer',
                'min:1',
            ],
            'penalty_value' => [
                'nullable',
                Rule::requiredIf(fn () => in_array($this->input('action_type'), [
                    InternalPenaltyType::FeatureRestriction->value,
                    InternalPenaltyType::HipScorePenalty->value,
                    InternalPenaltyType::ContentRemoval->value,
                ])),
                function ($attribute, $value, $fail) {
                    $actionType = $this->input('action_type');
                    if ($actionType === InternalPenaltyType::FeatureRestriction->value) {
                        if (!in_array($value, array_map(fn ($f) => $f->value, RestrictedFeature::cases()))) {
                            $fail("The selected feature restriction key is invalid.");
                        }
                    } elseif ($actionType === InternalPenaltyType::HipScorePenalty->value) {
                        if (!is_numeric($value)) {
                            $fail("The penalty points must be a valid number.");
                            return;
                        }
                        if (!preg_match('/^\d+(\.\d{1,2})?$/', (string) $value)) {
                            $fail("The penalty points may not have more than 2 decimal places.");
                            return;
                        }
                        $floatVal = (float) $value;
                        if ($floatVal < 1.00) {
                            $fail("The penalty points must be at least 1.00.");
                        } elseif ($floatVal > 36825.00) {
                            $fail("The penalty points may not be greater than 36,825.00.");
                        }
                    } elseif ($actionType === InternalPenaltyType::ContentRemoval->value) {
                        try {
                            app(ContentRemovalService::class)->parseIdentifier((string) $value);
                        } catch (\DomainException $e) {
                            $fail($e->getMessage());
                        }
                    }
                },
            ],
            'restriction_duration_type' => [
                'nullable',
                'string',
                Rule::requiredIf(fn () => in_array($this->input('action_type'), [
                    InternalPenaltyType::FeatureRestriction->value,
                    InternalPenaltyType::ProfessionalEligibilitySuspension->value,
                    InternalPenaltyType::JuryPanelDeactivation->value,
                ])),
                Rule::in(['temporary', 'permanent']),
            ],
            'duration_days' => [
                'nullable',
                'integer',
                'min:1',
                'max:365',
                Rule::requiredIf(fn () =>
                    $this->input('action_type') === InternalPenaltyType::TemporarySuspension->value
                    || (in_array($this->input('action_type'), [
                        InternalPenaltyType::FeatureRestriction->value,
                        InternalPenaltyType::ProfessionalEligibilitySuspension->value,
                        InternalPenaltyType::JuryPanelDeactivation->value,
                    ]) && $this->input('restriction_duration_type') === 'temporary')
                ),
            ],
            'reason' => ['required', 'string', 'min:5', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $report = $this->route('report');
            $statusVal = $report?->status instanceof \BackedEnum ? $report->status->value : (string) $report?->status;
            $actionType = $this->input('action_type');
            $targetUser = $report?->reportedUser;

            // Report status safety: feature restrictions, suspensions, professional discipline, jury panel deactivation, hip score penalty, and content removals require 'Valid' status
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
                $validator->errors()->add('report', "Penalties, feature restrictions, professional discipline, and score penalties can only be applied to reports with 'Valid' status. Current status is '{$statusVal}'.");
            }

            if (in_array($actionType, [
                InternalPenaltyType::TemporarySuspension->value,
                InternalPenaltyType::PermanentSuspension->value,
            ])) {
                if ($targetUser?->isAdmin()) {
                    $validator->errors()->add('action_type', 'Super Administrators cannot be suspended.');
                }

                if ($targetUser?->isJuryPanelAccount()) {
                    $validator->errors()->add('action_type', 'Jury Panel accounts cannot be suspended through user account suspension. Use the Jury Panel management portal.');
                }
            }

            if ($actionType === InternalPenaltyType::FeatureRestriction->value) {
                if ($targetUser?->isAdmin()) {
                    $validator->errors()->add('action_type', 'Super Administrators cannot receive feature restrictions.');
                }

                if ($targetUser?->isJuryPanelAccount()) {
                    $validator->errors()->add('action_type', 'Jury Panel accounts cannot receive feature restrictions. Use the Jury Panel management portal.');
                }

                // Prevent duplicate active restrictions for the same user and feature
                $featureKey = $this->input('penalty_value');
                if ($targetUser && $featureKey && app(AccountFeatureRestrictionService::class)->isRestricted($targetUser, $featureKey)) {
                    $validator->errors()->add('penalty_value', "This user already has an active restriction for '{$featureKey}'. You must reverse or wait for the existing restriction to expire before applying a new one.");
                }
            }

            if (in_array($actionType, [
                InternalPenaltyType::VerificationRevoked->value,
                InternalPenaltyType::ProfessionalEligibilitySuspension->value,
            ])) {
                if ($targetUser?->isAdmin()) {
                    $validator->errors()->add('action_type', 'Super Administrators cannot receive professional discipline sanctions.');
                }

                if ($targetUser?->isJuryPanelAccount()) {
                    $validator->errors()->add('action_type', 'Jury Panel accounts cannot receive professional discipline sanctions.');
                }

                $verification = $targetUser?->latestProfessionalVerification;
                if (!$verification) {
                    $validator->errors()->add('action_type', 'The reported user does not have a professional verification profile.');
                }

                if ($actionType === InternalPenaltyType::VerificationRevoked->value) {
                    $verificationStatus = $verification?->verification_status instanceof \BackedEnum
                        ? $verification->verification_status->value
                        : (string) $verification?->verification_status;

                    if ($verificationStatus === \App\Enums\ProfessionalVerificationStatus::Suspended->value) {
                        $validator->errors()->add('action_type', 'This user\'s professional verification is already suspended or revoked.');
                    }
                }

                if ($actionType === InternalPenaltyType::ProfessionalEligibilitySuspension->value) {
                    if ($targetUser && app(\App\Services\InternalTribunal\AccountProfessionalDisciplineService::class)->hasActiveEligibilitySuspension($targetUser)) {
                        $validator->errors()->add('action_type', 'This user already has an active professional eligibility suspension.');
                    }
                }
            }

            if ($actionType === InternalPenaltyType::JuryPanelDeactivation->value) {
                if ($targetUser?->isAdmin()) {
                    $validator->errors()->add('action_type', 'Super Administrators cannot receive Jury Panel deactivation.');
                }

                if (!$targetUser?->isJuryPanelAccount()) {
                    $validator->errors()->add('action_type', 'Jury Panel Deactivation can only be applied to institutional Jury Panel accounts.');
                }

                if ($targetUser && app(\App\Services\InternalTribunal\AccountJuryPanelDisciplineService::class)->hasActiveDeactivation($targetUser)) {
                    $validator->errors()->add('action_type', 'This Jury Panel already has an active deactivation penalty.');
                }
            }

            if ($actionType === InternalPenaltyType::HipScorePenalty->value) {
                if ($targetUser?->isAdmin()) {
                    $validator->errors()->add('action_type', 'Super Administrators cannot receive HIP score penalties.');
                }

                if ($targetUser?->isJuryPanelAccount()) {
                    $validator->errors()->add('action_type', 'Jury Panel accounts cannot receive HIP score penalties.');
                }

                if ($report && $targetUser) {
                    $hasActiveScorePenalty = \App\Models\InternalPenalty::where('internal_report_id', $report->id)
                        ->where('user_id', $targetUser->id)
                        ->where('action_type', InternalPenaltyType::HipScorePenalty->value)
                        ->whereNull('reversed_at')
                        ->exists();

                    if ($hasActiveScorePenalty) {
                        $validator->errors()->add('action_type', 'An active HIP / Score Penalty from this report has already been applied to this user.');
                    }
                }
            }

            if ($actionType === InternalPenaltyType::ContentRemoval->value) {
                if ($targetUser?->isAdmin()) {
                    $validator->errors()->add('action_type', 'Super Administrators cannot receive content removal sanctions.');
                }

                if ($targetUser?->isJuryPanelAccount()) {
                    $validator->errors()->add('action_type', 'Jury Panel accounts cannot receive content removal sanctions.');
                }

                $contentType = $this->input('content_type');
                $contentId = $this->input('content_id');

                if ($contentType && is_numeric($contentId) && (int) $contentId > 0 && $targetUser) {
                    try {
                        app(ContentRemovalService::class)->validateCanRemove($contentType, (int) $contentId, $targetUser);
                    } catch (\DomainException $e) {
                        $validator->errors()->add('content_id', $e->getMessage());
                    }

                    // Prevent duplicate active penalty for the same report and content
                    if ($report && $targetUser) {
                        $penaltyVal = "{$contentType}:{$contentId}";
                        $hasActiveRemoval = \App\Models\InternalPenalty::where('internal_report_id', $report->id)
                            ->where('user_id', $targetUser->id)
                            ->where('action_type', InternalPenaltyType::ContentRemoval->value)
                            ->where('penalty_value', $penaltyVal)
                            ->whereNull('reversed_at')
                            ->exists();

                        if ($hasActiveRemoval) {
                            $validator->errors()->add('action_type', 'An active Content Removal penalty for this content item already exists for this report.');
                        }
                    }
                }
            }
        });
    }
}
