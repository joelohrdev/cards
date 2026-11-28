<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\UpdateChecklistCard;
use App\Http\Controllers\Controller;
use App\Models\Card;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CardController extends Controller
{
    /**
     * Correct a checklist card's details.
     */
    public function update(Request $request, Card $card, UpdateChecklistCard $updateChecklistCard): RedirectResponse
    {
        $details = $request->validate(UpdateChecklistCard::rules($card));

        $updateChecklistCard->handle($card, $details);

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

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Card removed from the set’s checklist.']);

        return back();
    }
}
