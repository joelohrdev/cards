<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Collection\SaveCollectionItem;
use App\Enums\RawCondition;
use App\Http\Controllers\Controller;
use App\Models\CardSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SetOwnershipController extends Controller
{
    /**
     * Checklist mode: add one copy of each ticked card to the collection.
     */
    public function store(Request $request, CardSet $set, SaveCollectionItem $saveCollectionItem): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'card_ids' => ['required', 'array', 'min:1', 'max:2000'],
            'card_ids.*' => ['integer', Rule::exists('cards', 'id')->where('card_set_id', $set->id)],
            'parallel_id' => ['nullable', 'integer', Rule::exists('parallels', 'id')->where('card_set_id', $set->id)],
            'raw_condition' => ['nullable', Rule::enum(RawCondition::class)],
            'storage_location_id' => ['nullable', 'integer', Rule::exists('storage_locations', 'id')],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ]);

        DB::transaction(function () use ($validated, $user, $saveCollectionItem): void {
            foreach (array_unique($validated['card_ids']) as $cardId) {
                $saveCollectionItem->handle($user, [
                    'card_id' => $cardId,
                    'parallel_id' => $validated['parallel_id'] ?? null,
                    'raw_condition' => $validated['raw_condition'] ?? null,
                    'storage_location_id' => $validated['storage_location_id'] ?? null,
                    'tags' => $validated['tags'] ?? [],
                ]);
            }
        });

        $count = count(array_unique($validated['card_ids']));
        Inertia::flash('toast', ['type' => 'success', 'message' => "Added {$count} ".str('card')->plural($count).' to your collection.']);

        return back();
    }
}
