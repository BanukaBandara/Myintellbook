<?php

namespace App\Http\Requests\Professional;

use Illuminate\Foundation\Http\FormRequest;

class SuspendProfessionalVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && (bool) auth()->user()->is_admin;
    }

    public function rules(): array
    {
        return [
            'suspension_reason' => ['required', 'string', 'min:10', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return [
            'suspension_reason.required' => 'A clear explanation for the professional suspension is required.',
            'suspension_reason.min' => 'The suspension explanation must be at least 10 characters long.',
        ];
    }
}
