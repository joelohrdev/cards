<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\ResolveCard;
use App\Http\Controllers\Controller;
use App\Models\CardSet;
use App\Support\ChecklistParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ChecklistController extends Controller
{
    /**
     * Add or update checklist cards from pasted text.
     */
    public function store(Request $request, CardSet $set, ChecklistParser $parser, ResolveCard $resolveCard): RedirectResponse
    {
        $validated = $request->validate([
            'checklist' => ['required', 'string', 'max:200000'],
        ]);

        $cards = $parser->parse($validated['checklist']);

        if ($cards === []) {
            return back()->withErrors(['checklist' => 'No cards found. Put one card per line, starting with its number.']);
        }

        DB::transaction(function () use ($cards, $set, $resolveCard): void {
            foreach ($cards as $card) {
                $resolveCard->handle($set, $card);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Saved '.count($cards).' checklist '.str('card')->plural(count($cards)).'.']);

        return back();
    }
}
