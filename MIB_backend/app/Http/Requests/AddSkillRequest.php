<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /add-skill: one skill or certification on the current user's profile.
 */
class AddSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'skill' => ['required', 'string', 'max:150'],
            // 0 licensed profession, 1 vocational certification, 2 recognized certification
            'type' => ['nullable', 'integer', 'in:0,1,2'],
        ];
    }
}
