<?php

namespace App\Http\Requests\Tribunal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTribunalCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'respondent_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::notIn([$this->user()->id]),
            ],

            'title' => [
                'required',
                'string',
                'max:180',
            ],

            'category' => [
                'required',
                'string',
                'max:100',
            ],

            'description' => [
                'required',
                'string',
                'min:20',
                'max:10000',
            ],

            'requested_resolution' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ];
    }
}
