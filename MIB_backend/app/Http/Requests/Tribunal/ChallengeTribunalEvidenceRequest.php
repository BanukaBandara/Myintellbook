<?php

namespace App\Http\Requests\Tribunal;

use Illuminate\Foundation\Http\FormRequest;

class ChallengeTribunalEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Please provide a reason for challenging this evidence.',
            'reason.min' => 'The challenge reason must be at least 10 characters long.',
            'reason.max' => 'The challenge reason must not exceed 5000 characters.',
        ];
    }
}
