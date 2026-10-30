<?php

namespace App\Http\Controllers\Selling;

use App\Actions\Grading\AddItemsToGradingSubmission;
use App\Concerns\NormalizesMoneyInput;
use App\Enums\ItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Selling\GradableItems;
use App\Models\CollectionItem;
use App\Models\GradingSubmission;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * The cards inside a grading submission.
 */
class GradingItemController extends Controller
{
    use NormalizesMoneyInput;

    /**
     * Add one or more raw cards to a submission.
     */
    public function store(Request $request, GradingSubmission $submission, AddItemsToGradingSubmission $addItems): RedirectResponse
    {
        self::ensureEditable($submission);

        $validated = $request->validate([
            'collection_item_ids' => ['required', 'array', 'min:1', 'max:500'],
            'collection_item_ids.*' => GradableItems::rules($submission),
        ], [
            'collection_item_ids.required' => 'Choose at least one card to add.',
            'collection_item_ids.*.exists' => GradableItems::MESSAGE,
        ]);

        $items = CollectionItem::query()->whereKey($validated['collection_item_ids'])->get();

        DB::transaction(fn () => $addItems->handle($submission, $items));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $items->count() === 1
                ? "Added {$items->first()->title()} to the {$submission->grader->value} submission."
                : "Added {$items->count()} cards to the {$submission->grader->value} submission.",
        ]);

        return back();
    }

    /**
     * Change a card's declared value or grading fee.
     */
    public function update(Request $request, GradingSubmission $submission, CollectionItem $item): RedirectResponse
    {
        self::ensureAttached($submission, $item);

        $this->normalizeMoneyInput($request, ['declared_value', 'fee']);

        $validated = $request->validate([
            'declared_value' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'fee' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);

        $submission->collectionItems()->updateExistingPivot($item->id, [
            'declared_value' => Money::toCents($validated['declared_value'] ?? null),
            'fee' => Money::toCents($validated['fee'] ?? null) ?? 0,
        ]);

        return back();
    }

    /**
     * Take a card out of a submission. A card that was out at the grader goes back to "Owned".
     */
    public function destroy(GradingSubmission $submission, CollectionItem $item): RedirectResponse
    {
        self::ensureAttached($submission, $item);
        self::ensureEditable($submission);

        DB::transaction(function () use ($submission, $item): void {
            $submission->collectionItems()->detach($item->id);

            if ($item->status === ItemStatus::AtGrading) {
                $item->update(['status' => ItemStatus::Owned]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Card removed from the submission.']);

        return back();
    }

    private static function ensureAttached(GradingSubmission $submission, CollectionItem $item): void
    {
        abort_unless($submission->collectionItems()->whereKey($item->id)->exists(), 404);
    }

    /**
     * @throws ValidationException
     */
    private static function ensureEditable(GradingSubmission $submission): void
    {
        if ($submission->isCompleted()) {
            throw ValidationException::withMessages([
                'collection_item_ids' => 'This submission is complete, so its cards can’t change. Start a new submission instead.',
            ]);
        }
    }
}
