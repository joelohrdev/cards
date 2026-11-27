<?php

namespace App\Http\Requests\Catalog;

use App\Enums\Sport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CardSetRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sport' => ['required', Rule::enum(Sport::class)],
            'year' => ['required', 'integer', 'between:1860,2100'],
            'season' => ['nullable', 'string', 'max:20'],
            'manufacturer' => ['nullable', 'required_without:name', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:150'],
            'card_count' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'checklist' => ['nullable', 'string', 'max:200000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'manufacturer.required_without' => 'Enter a brand or a set name.',
        ];
    }
}
