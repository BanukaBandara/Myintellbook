<?php

namespace App\Http\Requests\InternalTribunal;

use App\Enums\InternalPenaltyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminApplyInternalPenaltyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'action_type' => [
                'required',
                'string',
                Rule::in(array_map(fn ($p) => $p->value, InternalPenaltyType::cases())),
            ],
            'reason' => ['required', 'string', 'min:5', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
