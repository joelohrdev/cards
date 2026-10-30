<?php

namespace App\Http\Requests\Data;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreImportRequest extends FormRequest
{
    /**
     * Largest accepted upload, in kilobytes (20 MB is far more than a 10,000-row card list).
     */
    public const int MAX_KILOBYTES = 20480;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,tsv', 'extensions:csv,txt,tsv', 'max:'.self::MAX_KILOBYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose a CSV file to upload.',
            'file.mimes' => 'Upload a CSV file. In Excel or Google Sheets use “Save as / Download → CSV”.',
            'file.extensions' => 'Upload a CSV file. In Excel or Google Sheets use “Save as / Download → CSV”.',
            'file.max' => 'That file is over 20 MB. Split it into smaller files and import each one.',
        ];
    }
}
