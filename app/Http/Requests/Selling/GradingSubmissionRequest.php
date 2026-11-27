<?php

namespace App\Http\Requests\Selling;

use App\Concerns\NormalizesMoneyInput;
use App\Enums\Grader;
use App\Enums\GradingStatus;
use App\Enums\ShippingCarrier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or edit a grading submission. Completing one goes through "Record results" instead.
 */
class GradingSubmissionRequest extends FormRequest
{
    use NormalizesMoneyInput;

    protected function prepareForValidation(): void
    {
        $this->normalizeMoneyInput($this, ['shipping_cost']);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'grader' => ['required', Rule::enum(Grader::class)],
            'service_level' => ['nullable', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(GradingStatus::class)->except([GradingStatus::Completed])],
            'submitted_on' => ['nullable', 'date', 'before_or_equal:today'],
            'outbound_carrier' => ['nullable', Rule::enum(ShippingCarrier::class)],
            'outbound_tracking' => ['nullable', 'string', 'max:100'],
            'received_on' => $this->dateOnOrAfter('submitted_on'),
            'shipped_back_on' => $this->dateOnOrAfter('received_on', 'submitted_on'),
            'return_carrier' => ['nullable', Rule::enum(ShippingCarrier::class)],
            'return_tracking' => ['nullable', 'string', 'max:100'],
            'returned_on' => $this->dateOnOrAfter('shipped_back_on', 'received_on', 'submitted_on'),
            'shipping_cost' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'collection_item_ids' => [Rule::prohibitedIf(! $this->isMethod('post')), 'nullable', 'array', 'max:500'],
            'collection_item_ids.*' => GradableItems::rules(),
        ];
    }

    /**
     * A past date that can't come before the latest earlier milestone that was filled in.
     * Comparing against an empty field would always fail, so only filled-in ones count.
     *
     * @return list<string>
     */
    private function dateOnOrAfter(string ...$earlierFields): array
    {
        $rules = ['nullable', 'date', 'before_or_equal:today'];
        $earlier = collect($earlierFields)->first(fn (string $field): bool => $this->filled($field));

        if ($earlier !== null) {
            $rules[] = "after_or_equal:{$earlier}";
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.enum' => 'Choose a status from the list. To finish a submission, use “Record results”.',
            'received_on.after_or_equal' => 'The grader can’t have received it before you sent it.',
            'shipped_back_on.after_or_equal' => 'It can’t have shipped back before the grader received it.',
            'returned_on.after_or_equal' => 'The return date can’t be before it was sent or shipped back.',
            '*.before_or_equal' => 'That date is in the future.',
            'collection_item_ids.*.exists' => GradableItems::MESSAGE,
        ];
    }
}
