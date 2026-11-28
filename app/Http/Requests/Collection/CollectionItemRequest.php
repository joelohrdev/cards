<?php

namespace App\Http\Requests\Collection;

use App\Actions\Catalog\UpdateChecklistCard;
use App\Concerns\NormalizesMoneyInput;
use App\Enums\Grader;
use App\Enums\GradingStatus;
use App\Enums\ItemStatus;
use App\Enums\RawCondition;
use App\Enums\Sport;
use App\Models\CollectionItem;
use App\Models\GradingSubmission;
use App\Support\StickerCodes;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CollectionItemRequest extends FormRequest
{
    use NormalizesMoneyInput;

    protected function prepareForValidation(): void
    {
        $this->normalizeMoneyInput($this, ['purchase_price', 'estimated_value']);

        if ($this->has('sticker_code')) {
            $this->merge(['sticker_code' => StickerCodes::normalize($this->input('sticker_code'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $item = $this->route('item');
        $checklistCardRules = ! $creating && $item instanceof CollectionItem && $this->filled('card_details')
            ? UpdateChecklistCard::rules($item->card, 'card_details.')
            : [];

        return [
            ...$checklistCardRules,
            'card_details' => [Rule::prohibitedIf($creating), 'nullable', 'array'],
            'card_id' => ['nullable', 'integer', 'exists:cards,id'],
            'card_set_id' => ['nullable', 'integer', 'exists:card_sets,id'],
            'new_set' => ['nullable', 'array'],
            'new_set.sport' => ['required_with:new_set', Rule::enum(Sport::class)],
            'new_set.year' => ['required_with:new_set', 'integer', 'between:1860,2100'],
            'new_set.manufacturer' => [Rule::requiredIf(fn (): bool => filled($this->input('new_set')) && blank($this->input('new_set.name'))), 'nullable', 'string', 'max:100'],
            'new_set.name' => ['nullable', 'string', 'max:150'],
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
            'sticker_code' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('collection_items', 'sticker_code')->ignore($this->route('item')),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ((int) $this->input('quantity', 1) > 1 && preg_match('/\d$/', (string) $value) !== 1) {
                        $fail('To give several copies their own stickers, use an ID ending in a number, like A0041.');
                    }
                },
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
            'tags' => ['nullable', 'array', 'max:30'],
            'tags.*' => ['string', 'max:50'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'grading' => [Rule::prohibitedIf(! $creating || $this->boolean('is_graded') || ! in_array($this->input('status'), [null, '', ItemStatus::Owned->value], true)), 'nullable', 'array'],
            'grading.plan' => ['required_with:grading', Rule::in(['going', 'sent'])],
            'grading.submission_id' => ['nullable', 'integer', $this->openSubmissionMatchingPlan()],
            'grading.grader' => [Rule::requiredIf(fn (): bool => filled($this->input('grading')) && blank($this->input('grading.submission_id'))), 'nullable', Rule::enum(Grader::class)],
            'grading.service_level' => ['nullable', 'string', 'max:100'],
            'grading.status' => ['nullable', Rule::in([GradingStatus::Submitted->value, GradingStatus::Received->value, GradingStatus::Grading->value, GradingStatus::Shipped->value])],
            'grading.submitted_on' => ['nullable', 'date', 'before_or_equal:today'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'back_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
        ];
    }

    /**
     * The chosen submission must still be open and match the plan: "going to send" cards join
     * a submission that hasn't been sent, "already sent" cards one that has.
     */
    private function openSubmissionMatchingPlan(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $submission = GradingSubmission::query()->open()->find((int) $value);
            $wantsSent = $this->input('grading.plan') === 'sent';

            if ($submission === null || $submission->isWithGrader() !== $wantsSent) {
                $fail('Choose one of the open submissions in the list.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'card.required' => 'Choose a card from the catalog or enter a new one.',
            'sticker_code.unique' => 'Another card already has that sticker ID.',
            'card_details.number.unique' => 'Another card in this set already has that number.',
            'card_details.number.required' => 'Enter the card number.',
            'card_details.name.required' => 'Enter the card’s name.',
            'card.number.required_with' => 'Enter the card number.',
            'new_set.manufacturer.required' => 'Enter a brand or a set name.',
            'grading.prohibited' => 'Only raw cards you have on hand can be sent for grading.',
            'grading.grader.required' => 'Choose the grading company.',
            'grading.submitted_on.before_or_equal' => 'The sent date can’t be in the future.',
        ];
    }
}
