<?php

namespace App\Http\Requests\Collection;

use App\Concerns\NormalizesMoneyInput;
use App\Enums\Grader;
use App\Enums\ItemStatus;
use App\Enums\RawCondition;
use App\Enums\Sport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CollectionItemRequest extends FormRequest
{
    use NormalizesMoneyInput;

    protected function prepareForValidation(): void
    {
        $this->normalizeMoneyInput($this, ['purchase_price', 'estimated_value']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'card_id' => ['nullable', 'integer', 'exists:cards,id'],
            'card_set_id' => ['nullable', 'integer', 'exists:card_sets,id'],
            'new_set' => ['nullable', 'array'],
            'new_set.sport' => ['required_with:new_set', Rule::enum(Sport::class)],
            'new_set.year' => ['required_with:new_set', 'integer', 'between:1860,2100'],
            'new_set.manufacturer' => ['nullable', 'string', 'max:100'],
            'new_set.name' => ['required_with:new_set', 'string', 'max:150'],
            'card' => [Rule::requiredIf(fn (): bool => blank($this->input('card_id'))), 'nullable', 'array'],
            'card.number' => ['required_with:card', 'string', 'max:30'],
            'card.name' => ['nullable', 'string', 'max:200'],
            'card.players' => ['nullable', 'array', 'max:10'],
            'card.players.*' => ['string', 'max:100'],
            'card.team' => ['nullable', 'string', 'max:100'],
            'card.subset' => ['nullable', 'string', 'max:100'],
            'card.is_rookie' => ['boolean'],
            'card.is_autograph' => ['boolean'],
            'card.is_memorabilia' => ['boolean'],
            'card.is_short_print' => ['boolean'],
            'card.print_run' => ['nullable', 'integer', 'min:1'],
            'parallel_id' => ['nullable', 'integer', 'exists:parallels,id'],
            'parallel_name' => ['nullable', 'string', 'max:100'],
            'parallel_print_run' => ['nullable', 'integer', 'min:1'],
            'serial_number' => ['nullable', 'integer', 'min:1'],
            'is_graded' => ['boolean'],
            'grader' => ['nullable', 'required_if:is_graded,true', Rule::enum(Grader::class)],
            'grade' => ['nullable', 'required_if:is_graded,true', 'string', 'max:20'],
            'cert_number' => ['nullable', 'string', 'max:50'],
            'raw_condition' => ['nullable', Rule::enum(RawCondition::class)],
            'status' => [$creating ? 'nullable' : 'required', Rule::enum(ItemStatus::class)],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_source' => ['nullable', 'string', 'max:150'],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'storage_location_id' => ['nullable', 'integer', Rule::exists('storage_locations', 'id')],
            'storage_position' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'tags' => ['nullable', 'array', 'max:30'],
            'tags.*' => ['string', 'max:50'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'back_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'card.required' => 'Choose a card from the catalog or enter a new one.',
            'card.number.required_with' => 'Enter the card number.',
        ];
    }
}
