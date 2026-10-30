<?php

namespace App\Actions\Grading;

use App\Enums\GradingStatus;
use App\Enums\ItemStatus;
use App\Models\CollectionItem;
use App\Models\GradingSubmission;

/**
 * Keeps the cards in a submission in step with where the submission is.
 *
 * Once a submission is sent, its cards are "At grading". Moving it back to "Preparing" returns
 * them to "Owned". Completing is handled by CompleteGradingSubmission.
 */
class SyncGradingItemStatuses
{
    public function handle(GradingSubmission $submission): void
    {
        $itemIds = $submission->collectionItems()->pluck('collection_items.id');

        if ($submission->isWithGrader()) {
            CollectionItem::query()
                ->whereKey($itemIds)
                ->whereIn('status', [ItemStatus::Owned->value, ItemStatus::Listed->value])
                ->update(['status' => ItemStatus::AtGrading->value]);

            return;
        }

        if ($submission->status === GradingStatus::Preparing) {
            CollectionItem::query()
                ->whereKey($itemIds)
                ->where('status', ItemStatus::AtGrading->value)
                ->update(['status' => ItemStatus::Owned->value]);
        }
    }
}
