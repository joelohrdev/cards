<?php

namespace App\Actions\Collection;

use App\Models\CollectionItem;
use App\Models\ValueSnapshot;

/**
 * Record today's collection totals so value can be charted over time.
 */
class CaptureValueSnapshot
{
    public function handle(): ValueSnapshot
    {
        $totals = CollectionItem::query()
            ->inHand()
            ->toBase()
            ->selectRaw('count(*) as item_count, coalesce(sum(estimated_value), 0) as total_value, coalesce(sum(purchase_price), 0) as total_cost')
            ->first();

        $snapshot = ValueSnapshot::query()
            ->whereDate('captured_on', today())
            ->first() ?? new ValueSnapshot(['captured_on' => today()]);

        $snapshot->fill([
            'item_count' => (int) $totals->item_count,
            'total_value' => (int) $totals->total_value,
            'total_cost' => (int) $totals->total_cost,
        ])->save();

        return $snapshot;
    }
}
