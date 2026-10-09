<?php

namespace App\Http\Requests\InternalTribunal;

use App\Enums\InternalReportStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUpdateInternalReportStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(array_map(fn ($s) => $s->value, InternalReportStatus::cases())),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
            'decision_reason' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
