<?php

namespace App\Http\Requests\Professional;

use App\Enums\ProfessionalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyProfessionalVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'profession_type' => [
                'required',
                'string',
                Rule::in(array_column(ProfessionalType::cases(), 'value')),
            ],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'enrollment_number' => ['nullable', 'string', 'max:100'],
            'issuing_authority' => ['required', 'string', 'min:3', 'max:255'],
            'years_of_experience' => ['required', 'integer', 'min:0', 'max:80'],
            'qualification_document' => [
                'required',
                'file',
                'max:10240', // 10MB
                'mimes:pdf,doc,docx,jpg,jpeg,png,webp',
            ],
            'identity_document' => [
                'required',
                'file',
                'max:10240', // 10MB
                'mimes:pdf,doc,docx,jpg,jpeg,png,webp',
            ],
            'additional_document' => [
                'nullable',
                'file',
                'max:10240', // 10MB
                'mimes:pdf,doc,docx,jpg,jpeg,png,webp',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'profession_type.required' => 'Please select your legal profession type.',
            'issuing_authority.required' => 'The issuing bar council, judicial body, or licensing authority is required.',
            'years_of_experience.required' => 'Please specify your years of active legal experience.',
            'qualification_document.required' => 'A valid qualification document (bar certificate, law degree, or appointment letter) is required.',
            'identity_document.required' => 'A valid professional identity document (bar card or judicial ID) is required.',
            'qualification_document.mimes' => 'Qualification document must be a PDF, Word document, or image (JPG, PNG, WEBP).',
            'identity_document.mimes' => 'Identity document must be a PDF, Word document, or image (JPG, PNG, WEBP).',
        ];
    }
}
