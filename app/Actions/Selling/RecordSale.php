<?php

namespace App\Actions\Selling;

use App\Enums\GradingStatus;
use App\Enums\ItemStatus;
use App\Models\CollectionItem;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Records a card's sale and marks the card sold.
 */
class RecordSale
{
    /**
     * @param  array{platform: string, sold_on: string, sale_price: int, fees: int, shipping_cost: int, buyer?: string|null, external_order_id?: string|null, notes?: string|null}  $data  Money in cents.
     */
    public function handle(User $user, CollectionItem $item, array $data): Sale
    {
        return DB::transaction(function () use ($user, $item, $data): Sale {
            $sale = $user->sales()->create([...$data, 'collection_item_id' => $item->id]);

            $item->update(['status' => ItemStatus::Sold]);

            $preparingSubmissionIds = $item->gradingSubmissions()
                ->where('grading_submissions.status', GradingStatus::Preparing->value)
                ->pluck('grading_submissions.id');

            if ($preparingSubmissionIds->isNotEmpty()) {
                $item->gradingSubmissions()->detach($preparingSubmissionIds);
            }

            return $sale;
        });
    }
}
