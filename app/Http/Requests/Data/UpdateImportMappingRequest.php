<?php

namespace App\Http\Requests\Data;

use App\Enums\Sport;
use App\Models\Import;
use App\Support\Import\ImportFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateImportMappingRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Import $import */
        $import = $this->route('import');
        $columnCount = count($import->headers);

        $rules = [
            'mapping' => ['required', 'array:'.implode(',', array_keys(ImportFields::FIELDS))],
            'defaults' => ['nullable', 'array'],
            'defaults.sport' => ['nullable', Rule::enum(Sport::class)],
            'defaults.tags' => ['nullable', 'array', 'max:10'],
            'defaults.tags.*' => ['nullable', 'string', 'max:50'],
            'defaults.storage_location_id' => ['nullable', 'integer', Rule::exists('storage_locations', 'id')],
        ];

        foreach (array_keys(ImportFields::FIELDS) as $field) {
            $rules["mapping.{$field}"] = in_array($field, ImportFields::required(), true)
                ? ['required', 'integer', 'between:0,'.($columnCount - 1)]
                : ['nullable', 'integer', 'between:0,'.($columnCount - 1)];
        }

        return $rules;
    }

    /**
     * A sport must come from somewhere: a column or the default.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('mapping.sport') === null && blank($this->input('defaults.sport'))) {
                    $validator->errors()->add('defaults.sport', 'Choose a default sport, or map a sport column.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mapping.year.required' => 'Choose the column that holds the year.',
            'mapping.set.required' => 'Choose the column that holds the set name.',
            'mapping.number.required' => 'Choose the column that holds the card number.',
        ];
    }

    /**
     * @return array{mapping: array<string, int|null>, defaults: array{sport: string|null, tags: array<int, string>, storage_location_id: int|null}}
     */
    public function importSettings(): array
    {
        $mapping = collect(array_keys(ImportFields::FIELDS))
            ->mapWithKeys(fn (string $field): array => [$field => $this->filled("mapping.{$field}") ? $this->integer("mapping.{$field}") : null])
            ->all();

        return [
            'mapping' => $mapping,
            'defaults' => [
                'sport' => $this->filled('defaults.sport') ? $this->string('defaults.sport')->toString() : null,
                'tags' => collect((array) $this->input('defaults.tags', []))->map(fn (?string $tag): string => trim((string) $tag))->filter()->unique()->values()->all(),
                'storage_location_id' => $this->filled('defaults.storage_location_id') ? $this->integer('defaults.storage_location_id') : null,
            ],
        ];
    }
}
