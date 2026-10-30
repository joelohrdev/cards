<?php

namespace App\Http\Requests\Ebay;

use App\Concerns\NormalizesMoneyInput;
use App\Services\Ebay\ListingContent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EbayListingRequest extends FormRequest
{
    use NormalizesMoneyInput;

    protected function prepareForValidation(): void
    {
        $this->normalizeMoneyInput($this, ['price']);

        if (is_string($this->input('title'))) {
            $this->merge(['title' => trim((string) preg_replace('/\s+/', ' ', $this->input('title')))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:'.ListingContent::TITLE_LIMIT],
            'description' => ['required', 'string', 'max:20000'],
            'price' => ['required', 'numeric', 'min:0.99', 'max:999999'],
            'publish' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.max' => 'eBay titles can be at most 80 characters. Shorten the title and try again.',
            'price.min' => 'eBay’s minimum price is $0.99.',
        ];
    }
}
