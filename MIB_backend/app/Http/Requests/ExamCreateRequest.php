<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /create_exam (legacy exam builder).
 */
class ExamCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'profession' => ['required', 'integer', 'exists:professions,id'],
            'level' => ['required', 'string', 'in:easy,medium,hard'],
            'NoQuestions' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
