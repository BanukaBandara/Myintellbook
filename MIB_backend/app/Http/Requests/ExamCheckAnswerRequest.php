<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /check_answer: body is the positional array [answer, questionId] sent by the practice UI.
 */
class ExamCheckAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            '0' => ['nullable', 'string', 'max:1000'],
            '1' => ['required', 'integer', 'exists:questions,id'],
        ];
    }
}
