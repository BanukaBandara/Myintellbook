<?php

namespace App\Http\Requests\Tribunal;

use App\Enums\TribunalEvidenceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreTribunalEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'type' => [
                'required',
                'string',
                Rule::in(array_column(TribunalEvidenceType::cases(), 'value')),
            ],
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => [
                'nullable',
                'string',
                'max:5000',
                Rule::requiredIf(fn () => $this->input('type') === TribunalEvidenceType::Statement->value && !$this->hasFile('file')),
            ],
            'external_url' => [
                'nullable',
                'url',
                'max:2048',
                Rule::requiredIf(fn () => $this->input('type') === TribunalEvidenceType::Link->value),
            ],
            'file' => [
                'nullable',
                Rule::requiredIf(fn () => in_array($this->input('type'), [
                    TribunalEvidenceType::Image->value,
                    TribunalEvidenceType::Document->value,
                    TribunalEvidenceType::Video->value,
                    TribunalEvidenceType::Audio->value,
                ], true)),
                'file',
                'max:20480', // 20 MB
                'mimes:jpg,jpeg,png,webp,pdf,doc,docx,txt,mp3,wav,mp4',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Please select an evidence type.',
            'title.required' => 'A title is required for this evidence.',
            'external_url.required' => 'External URL is required for link evidence.',
            'description.required' => 'A statement text is required when submitting statement evidence.',
            'file.required' => 'A file upload is required for this evidence type.',
            'file.mimes' => 'The uploaded file format is not permitted. Allowed formats: images (jpg, png, webp), documents (pdf, doc, docx, txt), media (mp3, wav, mp4).',
            'file.max' => 'The file size must not exceed 20MB.',
        ];
    }
}
