<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkExperianceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title'=>'required',
            'company'=>'required',
            'currently_working'=>'required',
            'location'=>'required',
            'selectEmpType'=> 'required|integer',
            'locationType'=> 'required|integer'
        ];
    }
}
