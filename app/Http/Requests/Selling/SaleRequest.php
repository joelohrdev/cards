<?php

namespace App\Http\Requests\Selling;

use App\Concerns\NormalizesMoneyInput;
use App\Enums\ItemStatus;
use App\Enums\SalePlatform;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Record the sale of an in-hand card.
 */
class SaleRequest extends FormRequest
{
    use NormalizesMoneyInput;

    protected function prepareForValidation(): void
    {
        $this->normalizeMoneyInput($this, ['sale_price', 'fees', 'shipping_cost']);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'collection_item_id' => [
                'required',
                'integer',
                Rule::exists('collection_items', 'id')->where(fn (Builder $query) => $query
                    ->whereIn('status', [ItemStatus::Owned->value, ItemStatus::Listed->value])),
                Rule::unique('sales', 'collection_item_id'),
            ],
            'platform' => ['required', Rule::enum(SalePlatform::class)],
            'sold_on' => ['required', 'date', 'before_or_equal:today'],
            'sale_price' => ['required', 'numeric', 'min:0.01', 'max:10000000'],
            'fees' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'buyer' => ['nullable', 'string', 'max:150'],
            'external_order_id' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'collection_item_id.exists' => 'Only cards you have on hand can be sold. Cards at grading need to come back first.',
            'collection_item_id.unique' => 'This card already has a sale recorded. Delete that sale on the Sales page to record a new one.',
            'sale_price.min' => 'Enter what the card sold for.',
        ];
    }
}
