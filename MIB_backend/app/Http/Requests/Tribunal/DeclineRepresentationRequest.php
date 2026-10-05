<?php

namespace App\Http\Requests\Tribunal;

use Illuminate\Foundation\Http\FormRequest;

class DeclineRepresentationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reason must be provided for declining the representation request.',
            'reason.min' => 'The decline reason must be at least 5 characters.',
        ];
    }
}
