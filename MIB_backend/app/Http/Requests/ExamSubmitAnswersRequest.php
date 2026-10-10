<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /submit_answers: answers for one exam attempt.
 */
class ExamSubmitAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exam' => ['required', 'integer', 'exists:exams,id'],
            'answers' => ['required', 'array', 'min:1', 'max:200'],
            'answers.*.qId' => ['required', 'integer', 'distinct', 'exists:questions,id'],
            'answers.*.answer' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
