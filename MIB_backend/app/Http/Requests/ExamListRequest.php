<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /get_exams and /my_exams filters.
 */
class ExamListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['nullable', 'integer', 'min:0'],
            'serchKey' => ['nullable', 'string', 'max:100'],
        ];
    }
}
