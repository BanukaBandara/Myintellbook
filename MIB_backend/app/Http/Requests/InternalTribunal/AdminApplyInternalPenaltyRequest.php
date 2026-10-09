<?php

namespace App\Http\Requests\InternalTribunal;

use App\Enums\InternalPenaltyType;
use App\Enums\InternalReportStatus;
use App\Enums\RestrictedFeature;
use App\Services\InternalTribunal\AccountFeatureRestrictionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminApplyInternalPenaltyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'action_type' => [
                'required',
                'string',
                Rule::in(array_map(fn ($p) => $p->value, InternalPenaltyType::cases())),
            ],
            'penalty_value' => [
                'nullable',
                'string',
                Rule::requiredIf(fn () => $this->input('action_type') === InternalPenaltyType::FeatureRestriction->value),
                Rule::in(array_map(fn ($f) => $f->value, RestrictedFeature::cases())),
            ],
            'restriction_duration_type' => [
                'nullable',
                'string',
                Rule::requiredIf(fn () => in_array($this->input('action_type'), [
                    InternalPenaltyType::FeatureRestriction->value,
                    InternalPenaltyType::ProfessionalEligibilitySuspension->value,
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

            // Report status safety: feature restrictions, suspensions, and professional discipline require 'Valid' status
            if (in_array($actionType, [
                InternalPenaltyType::FeatureRestriction->value,
                InternalPenaltyType::TemporarySuspension->value,
                InternalPenaltyType::PermanentSuspension->value,
                InternalPenaltyType::VerificationRevoked->value,
                InternalPenaltyType::ProfessionalEligibilitySuspension->value,
            ]) && $statusVal !== InternalReportStatus::Valid->value) {
                $validator->errors()->add('report', "Penalties, feature restrictions, and professional discipline can only be applied to reports with 'Valid' status. Current status is '{$statusVal}'.");
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
        });
    }
}
