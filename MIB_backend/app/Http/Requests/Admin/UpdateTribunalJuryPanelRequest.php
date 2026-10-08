<?php

namespace App\Http\Requests\Admin;

use App\Enums\TribunalJuryPanelStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTribunalJuryPanelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && (bool) auth()->user()->is_admin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'panel_name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::enum(TribunalJuryPanelStatus::class)],
        ];
    }
}
