<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * POST /submit-complains (legacy complaint flow).
 * The complainer is always the authenticated user; they cannot file against themselves,
 * and the juror can be neither the complainer nor the defendant.
 */
class SubmitComplainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'defendent' => ['required', 'integer', 'exists:users,id'],
            'category' => ['required', 'string', 'max:255'],
            'from' => ['required', 'integer', 'in:1,2'],
            'jury' => ['required', 'integer', 'exists:users,id', 'different:defendent'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $selfId = (int) $this->user()?->id;

            if ((int) $this->input('defendent') === $selfId) {
                $validator->errors()->add('defendent', 'You cannot file a complaint against yourself.');
            }
            if ((int) $this->input('jury') === $selfId) {
                $validator->errors()->add('jury', 'You cannot act as the juror on your own complaint.');
            }
        });
    }
}
