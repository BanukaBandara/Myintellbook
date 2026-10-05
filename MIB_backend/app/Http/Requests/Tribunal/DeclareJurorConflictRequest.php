<?php

namespace App\Http\Requests\Tribunal;

use Illuminate\Foundation\Http\FormRequest;

class DeclareJurorConflictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'has_conflict' => ['required', 'boolean'],
            'conflict_reason' => [
                'nullable',
                'string',
                'max:5000',
                'required_if:has_conflict,true,1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'has_conflict.required' => 'Conflict declaration status is required.',
            'conflict_reason.required_if' => 'Please provide a detailed reason if you have a conflict of interest.',
        ];
    }
}
