<?php

namespace App\Http\Controllers\Organize;

use App\Http\Controllers\Controller;
use App\Http\Resources\CardResource;
use App\Models\Card;
use App\Models\WantListEntry;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WantListController extends Controller
{
    /**
     * Show the want list.
     */
    public function index(Request $request): Response
    {
        $entries = WantListEntry::query()
            ->with(['card.cardSet.manufacturer', 'card.players', 'card.team', 'parallel'])
            ->orderByDesc('priority')
            ->latest()
            ->get()
            ->map(fn (WantListEntry $entry): array => [
                'id' => $entry->id,
                'card' => CardResource::make($entry->card)->resolve(),
                'parallel' => $entry->parallel?->name,
                'max_price' => $entry->max_price,
                'priority' => $entry->priority,
                'notes' => $entry->notes,
            ]);

        return Inertia::render('organize/want-list', [
            'entries' => $entries,
        ]);
    }

    /**
     * Add cards to the want list (one or many from a set checklist).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'card_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'card_ids.*' => ['integer', 'exists:cards,id'],
            'parallel_id' => ['nullable', 'integer', 'exists:parallels,id'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'priority' => ['nullable', 'integer', 'between:1,3'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach ($validated['card_ids'] as $cardId) {
            WantListEntry::query()->updateOrCreate(
                ['card_id' => $cardId, 'parallel_id' => $validated['parallel_id'] ?? null],
                [
                    'user_id' => $request->user()->id,
                    'max_price' => Money::toCents($validated['max_price'] ?? null),
                    'priority' => $validated['priority'] ?? 2,
                    'notes' => $validated['notes'] ?? null,
                ],
            );
        }

        $count = count($validated['card_ids']);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Added {$count} ".str('card')->plural($count).' to your want list.']);

        return back();
    }

    /**
     * Update a want list entry.
     */
    public function update(Request $request, WantListEntry $wantListEntry): RedirectResponse
    {
        $validated = $request->validate([
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'priority' => ['required', 'integer', 'between:1,3'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $wantListEntry->update([...$validated, 'max_price' => Money::toCents($validated['max_price'] ?? null)]);

        return back();
    }

    /**
     * Remove an entry, e.g. once the card has been acquired.
     */
    public function destroy(WantListEntry $wantListEntry): RedirectResponse
    {
        $wantListEntry->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Removed from your want list.']);

        return back();
    }
}
