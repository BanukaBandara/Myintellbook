<?php

namespace App\Http\Requests\Tribunal;

use Illuminate\Foundation\Http\FormRequest;

class EndMediationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
