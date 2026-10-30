<?php

namespace App\Http\Requests\Data;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShareLinkRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'tag_id' => ['nullable', 'integer', Rule::exists('tags', 'id')],
            'show_values' => ['boolean'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tag_id.exists' => 'Choose one of your tags.',
            'expires_at.after' => 'Pick an expiry date after today.',
        ];
    }

    /**
     * @return array{title: string, description: string|null, tag_id: int|null, show_values: bool, expires_at: string|null}
     */
    public function shareLinkAttributes(): array
    {
        return [
            'title' => $this->string('title')->trim()->toString(),
            'description' => $this->filled('description') ? $this->string('description')->trim()->toString() : null,
            'tag_id' => $this->filled('tag_id') ? $this->integer('tag_id') : null,
            'show_values' => $this->boolean('show_values'),
            'expires_at' => $this->filled('expires_at') ? $this->date('expires_at')?->endOfDay()->toDateTimeString() : null,
        ];
    }
}
