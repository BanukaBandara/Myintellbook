<?php

namespace App\Http\Requests\Tribunal;

use Illuminate\Foundation\Http\FormRequest;

class SendCaseRoomMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'related_evidence_id' => ['nullable', 'integer', 'exists:tribunal_evidence,id'],
        ];
    }
}
