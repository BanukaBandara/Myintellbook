<?php

namespace App\Http\Requests\Tribunal;

use Illuminate\Foundation\Http\FormRequest;

class RequestRepresentationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'representative_user_id' => ['required', 'integer', 'exists:users,id'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'representative_user_id.required' => 'Please select a verified legal representative.',
            'representative_user_id.exists' => 'The selected representative does not exist.',
        ];
    }
}
