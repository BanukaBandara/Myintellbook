<?php

namespace App\Http\Requests\Professional;

use Illuminate\Foundation\Http\FormRequest;

class RejectProfessionalVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && (bool) auth()->user()->is_admin;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:10', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'A clear rejection reason must be provided to the applicant.',
            'rejection_reason.min' => 'The rejection explanation must be at least 10 characters long.',
        ];
    }
}
