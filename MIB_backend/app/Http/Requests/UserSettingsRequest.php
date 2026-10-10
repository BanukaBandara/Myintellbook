<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * POST /set_settings: per-field profile visibility. Only the known keys are accepted and each
 * value must be a known visibility level ('' means "not set", treated as Public).
 */
class UserSettingsRequest extends FormRequest
{
    /** Keys sent by AccountSettings/PrivacyInfo.vue and read by App\Support\ProfileVisibility. */
    public const KEYS = [
        'first_name', 'last_name', 'birth_date', 'gender',
        'title', 'organization', 'location', 'employment_type', 'location_type',
        'school', 'degree', 'field_of_study', 'skills',
    ];

    public const VALUES = ['', 'Public', 'Only Me'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // The UI sends '' for "not chosen"; keep that as an explicit empty string.
        $this->merge(collect($this->only(self::KEYS))->map(fn ($v) => $v ?? '')->all());
    }

    public function rules(): array
    {
        return collect(self::KEYS)
            ->mapWithKeys(fn (string $key) => [$key => ['sometimes', 'nullable', 'string', 'in:'.implode(',', self::VALUES)]])
            ->all();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $unknown = array_diff(array_keys($this->all()), self::KEYS);
            foreach ($unknown as $key) {
                $validator->errors()->add((string) $key, 'Unknown setting.');
            }
        });
    }

    /** Only the allowlisted keys that were actually sent. */
    public function settings(): array
    {
        return collect($this->validated())->only(self::KEYS)->map(fn ($v) => (string) ($v ?? ''))->all();
    }
}
