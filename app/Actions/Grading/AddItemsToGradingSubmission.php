<?php

namespace App\Actions\Grading;

use App\Enums\ItemStatus;
use App\Models\CollectionItem;
use App\Models\GradingSubmission;
use Illuminate\Support\Collection;

/**
 * Adds raw cards to a submission, defaulting each card's declared value to its estimated value
 * and its fee to the fee of the last card added to the same submission.
 */
class AddItemsToGradingSubmission
{
    /**
     * @param  Collection<int, CollectionItem>  $items
     */
    public function handle(GradingSubmission $submission, Collection $items): void
    {
        $defaultFee = (int) ($submission->collectionItems()
            ->orderByPivot('id', 'desc')
            ->first()
            ?->getRelation('pivot')
            ->fee ?? 0);

        $alreadyAdded = $submission->collectionItems()->pluck('collection_items.id')->all();

        $attach = $items
            ->reject(fn (CollectionItem $item): bool => in_array($item->id, $alreadyAdded, true))
            ->mapWithKeys(fn (CollectionItem $item): array => [$item->id => [
                'declared_value' => $item->estimated_value,
                'fee' => $defaultFee,
            ]]);

        if ($attach->isEmpty()) {
            return;
        }

        $submission->collectionItems()->attach($attach->all());

        if ($submission->isWithGrader()) {
            CollectionItem::query()
                ->whereKey($attach->keys())
                ->update(['status' => ItemStatus::AtGrading->value]);
        }
    }
}
