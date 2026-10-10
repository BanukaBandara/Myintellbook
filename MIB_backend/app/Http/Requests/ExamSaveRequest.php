<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /save_exam: bookmark or un-bookmark an exam.
 */
class ExamSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ExamSavedIndex' => ['required', 'integer', 'exists:exams,id'],
            'isBooked' => ['required', 'boolean'],
        ];
    }
}
