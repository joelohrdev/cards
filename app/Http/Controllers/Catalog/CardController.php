<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CardController extends Controller
{
    /**
     * Correct a checklist card's details.
     */
    public function update(Request $request, Card $card): RedirectResponse
    {
        $validated = $request->validate([
            'number' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:200'],
            'players' => ['nullable', 'array', 'max:10'],
            'players.*' => ['string', 'max:100'],
            'team' => ['nullable', 'string', 'max:100'],
            'subset' => ['nullable', 'string', 'max:100'],
            'is_rookie' => ['boolean'],
            'is_autograph' => ['boolean'],
            'is_memorabilia' => ['boolean'],
            'is_short_print' => ['boolean'],
            'print_run' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $sport = $card->cardSet->sport;

        $card->update([
            ...Arr::except($validated, ['players', 'team']),
            'team_id' => filled($validated['team'] ?? null)
                ? Team::query()->firstOrCreate(['name' => Str::squish($validated['team']), 'sport' => $sport])->id
                : null,
        ]);

        if (array_key_exists('players', $validated)) {
            /** @var list<string> $players */
            $players = $validated['players'] ?? [];

            $card->players()->sync(collect($players)
                ->map(fn (string $name): string => Str::squish($name))
                ->filter()
                ->unique()
                ->map(fn (string $name): int => Player::query()->firstOrCreate(['name' => $name, 'sport' => $sport])->id)
                ->all());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Card details updated.']);

        return back();
    }

    /**
     * Remove a checklist card nobody owns.
     */
    public function destroy(Card $card): RedirectResponse
    {
        if ($card->collectionItems()->exists()) {
            return back()->withErrors(['card' => 'This card is in a collection, so it can’t be deleted.']);
        }

        $card->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Checklist card removed.']);

        return back();
    }
}
