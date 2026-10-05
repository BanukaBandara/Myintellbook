<?php

namespace App\Http\Requests\Tribunal;

use App\Enums\TribunalResponsePosition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitTribunalResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'position' => [
                'required',
                Rule::enum(TribunalResponsePosition::class),
            ],

            'response_text' => [
                'required',
                'string',
                'min:20',
                'max:10000',
            ],
        ];
    }
}
