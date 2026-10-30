<?php

namespace App\Http\Requests\Selling;

use App\Concerns\NormalizesMoneyInput;
use App\Enums\Grader;
use App\Enums\GradingStatus;
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
            'returned_on' => ['nullable', 'date', 'after_or_equal:submitted_on'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'collection_item_ids' => [Rule::prohibitedIf(! $this->isMethod('post')), 'nullable', 'array', 'max:500'],
            'collection_item_ids.*' => GradableItems::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.enum' => 'Choose a status from the list. To finish a submission, use “Record results”.',
            'returned_on.after_or_equal' => 'The return date can’t be before the date you sent it.',
            'collection_item_ids.*.exists' => GradableItems::MESSAGE,
        ];
    }
}
