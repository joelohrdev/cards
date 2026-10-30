<?php

namespace App\Http\Requests\Selling;

use App\Enums\ItemStatus;
use App\Models\GradingSubmission;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Validation for cards being added to a grading submission: they must be raw,
 * in-hand cards that aren't already in another open submission.
 */
final class GradableItems
{
    public const string MESSAGE = 'Only raw cards you have on hand can be sent for grading. Graded, sold, or already-submitted cards can’t be added.';

    /**
     * @return array<int, mixed>
     */
    public static function rules(?GradingSubmission $except = null): array
    {
        return [
            'integer',
            self::exists(),
            function (string $attribute, mixed $value, Closure $fail) use ($except): void {
                $inOpenSubmission = GradingSubmission::query()
                    ->open()
                    ->when($except, fn ($query, GradingSubmission $submission) => $query->whereKeyNot($submission->id))
                    ->whereHas('collectionItems', fn ($items) => $items->whereKey((int) $value))
                    ->exists();

                if ($inOpenSubmission) {
                    $fail('That card is already in another open grading submission.');
                }
            },
        ];
    }

    private static function exists(): Exists
    {
        return Rule::exists('collection_items', 'id')->where(fn (Builder $query) => $query
            ->where('is_graded', false)
            ->where('status', ItemStatus::Owned->value));
    }
}
