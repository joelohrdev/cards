<?php

namespace App\Http\Requests\Ebay;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EbayPoliciesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fulfillment_policy_id' => ['required', 'string', 'max:100'],
            'payment_policy_id' => ['required', 'string', 'max:100'],
            'return_policy_id' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fulfillment_policy_id.required' => 'Choose a shipping policy.',
            'payment_policy_id.required' => 'Choose a payment policy.',
            'return_policy_id.required' => 'Choose a return policy.',
        ];
    }
}
