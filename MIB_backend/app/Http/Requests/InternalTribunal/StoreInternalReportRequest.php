<?php

namespace App\Http\Requests\InternalTribunal;

use App\Enums\InternalReportCategory;
use App\Models\InternalReport;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInternalReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'reported_user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::notIn([$this->user()?->id]),
                function ($attribute, $value, $fail) {
                    $targetUser = User::find($value);
                    if (!$targetUser) {
                        return;
                    }

                    if ($targetUser->isAdmin()) {
                        $fail('Super Administrators cannot be reported through internal user reporting.');
                        return;
                    }

                    if ($targetUser->juryPanel && $this->input('category') !== InternalReportCategory::TribunalLegalProcessMisconduct->value) {
                        $fail('Jury Panel members may only be reported under the "Tribunal / Legal Process Misconduct" category.');
                        return;
                    }

                    // Duplicate spam check: cannot submit duplicate active report for same user in same category
                    $existingActive = InternalReport::where('reporter_user_id', $this->user()?->id)
                        ->where('reported_user_id', $value)
                        ->where('category', $this->input('category'))
                        ->whereNotIn('status', ['Closed', 'Invalid'])
                        ->exists();

                    if ($existingActive) {
                        $fail('You already have an active report under review for this user and category.');
                    }
                },
            ],
            'category' => [
                'required',
                'string',
                Rule::in(array_map(fn ($c) => $c->value, InternalReportCategory::cases())),
            ],
            'subject' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['required', 'string', 'min:10', 'max:10000'],
            'severity' => ['nullable', 'string', 'in:low,medium,high,critical'],
            'evidence' => ['nullable', 'array', 'max:5'],
            'evidence.*' => [
                'file',
                'max:10240', // 10MB
                'mimes:pdf,doc,docx,txt,jpg,jpeg,png,webp',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reported_user_id.not_in' => 'You cannot report your own account.',
            'reported_user_id.exists' => 'The selected reported user does not exist.',
            'evidence.*.max' => 'Each evidence file must not exceed 10 MB.',
            'evidence.*.mimes' => 'Evidence files must be in PDF, Word, text, or image (JPEG/PNG/WEBP) format.',
        ];
    }
}
