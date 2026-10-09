<?php

namespace App\Http\Requests\InternalTribunal;

use App\Enums\InternalPenaltyType;
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
            'duration_days' => [
                'nullable',
                'integer',
                'min:1',
                'max:365',
                Rule::requiredIf(fn () => $this->input('action_type') === InternalPenaltyType::TemporarySuspension->value),
            ],
            'reason' => ['required', 'string', 'min:5', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $actionType = $this->input('action_type');

            if (in_array($actionType, [
                InternalPenaltyType::TemporarySuspension->value,
                InternalPenaltyType::PermanentSuspension->value,
            ])) {
                $report = $this->route('report');
                $targetUser = $report?->reportedUser;

                if ($targetUser?->isAdmin()) {
                    $validator->errors()->add('action_type', 'Super Administrators cannot be suspended.');
                }

                if ($targetUser?->isJuryPanelAccount()) {
                    $validator->errors()->add('action_type', 'Jury Panel accounts cannot be suspended through user account suspension. Use the Jury Panel management portal.');
                }
            }
        });
    }
}
