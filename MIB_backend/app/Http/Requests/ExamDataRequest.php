<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /get_exam_data: questions of one exam.
 */
class ExamDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'examId' => ['required', 'integer', 'exists:exams,id'],
            'from' => ['nullable', 'string', 'max:20'],
        ];
    }
}
