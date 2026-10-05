<?php

namespace App\Http\Requests\Tribunal;

use Illuminate\Foundation\Http\FormRequest;

class AskAdjudicatorQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'target_side' => ['required', 'string', 'in:complainant,respondent,both'],
        ];
    }
}
