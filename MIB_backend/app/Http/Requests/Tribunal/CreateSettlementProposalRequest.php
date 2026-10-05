<?php

namespace App\Http\Requests\Tribunal;

use Illuminate\Foundation\Http\FormRequest;

class CreateSettlementProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'terms' => ['required', 'string', 'max:10000'],
        ];
    }
}
