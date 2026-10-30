<?php

namespace App\Http\Controllers\Selling;

use App\Enums\ItemStatus;
use App\Http\Controllers\Controller;
use App\Models\CollectionItem;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Marks a card as traded away. Trades have no proceeds, so no sale is recorded.
 */
class TradeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'collection_item_id' => [
                'required',
                'integer',
                Rule::exists('collection_items', 'id')->where(fn (Builder $query) => $query
                    ->whereIn('status', [ItemStatus::Owned->value, ItemStatus::Listed->value])),
            ],
        ], [
            'collection_item_id.exists' => 'Only cards you have on hand can be traded.',
        ]);

        $item = CollectionItem::query()->findOrFail($request->integer('collection_item_id'));
        $item->update(['status' => ItemStatus::Traded]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Marked {$item->title()} as traded."]);

        return back();
    }
}
