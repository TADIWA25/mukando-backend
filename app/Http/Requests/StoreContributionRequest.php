<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContributionRequest extends FormRequest
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
            'group_type' => ['required', 'string', 'max:255'],
            'target_amount' => [
                'required_if:group_type,contribution',
                'prohibited_unless:group_type,contribution',
                'numeric',
                'gt:0',
            ],
        ];
    }
}
