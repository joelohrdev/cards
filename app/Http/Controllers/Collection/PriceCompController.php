<?php

namespace App\Http\Controllers\Collection;

use App\Concerns\NormalizesMoneyInput;
use App\Enums\Grader;
use App\Http\Controllers\Controller;
use App\Models\CollectionItem;
use App\Models\PriceComp;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PriceCompController extends Controller
{
    use NormalizesMoneyInput;

    /**
     * Record a sold comp for an item's card, optionally adopting it as the item's value.
     */
    public function store(Request $request, CollectionItem $item): RedirectResponse
    {
        $this->normalizeMoneyInput($request, ['price']);

        $validated = $request->validate([
            'price' => ['required', 'numeric', 'min:0.01', 'max:10000000'],
            'sold_on' => ['required', 'date', 'before_or_equal:today'],
            'grader' => ['nullable', Rule::enum(Grader::class)],
            'grade' => ['nullable', 'string', 'max:20'],
            'parallel_id' => ['nullable', 'integer', Rule::exists('parallels', 'id')->where('card_set_id', $item->card->card_set_id)],
            'source' => ['nullable', 'string', 'max:100'],
            'url' => ['nullable', 'url', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'apply_to_item' => ['boolean'],
        ]);

        $comp = $request->user()->priceComps()->create([
            ...Arr::except($validated, 'apply_to_item'),
            'card_id' => $item->card_id,
            'price' => Money::toCents($validated['price']),
        ]);

        if ($request->boolean('apply_to_item')) {
            $item->update(['estimated_value' => $comp->price, 'value_updated_at' => now()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Comp saved.']);

        return back();
    }

    /**
     * Set the item's value to the average of the comps that match its parallel and grade.
     */
    public function apply(Request $request, CollectionItem $item): RedirectResponse
    {
        $average = PriceComp::query()
            ->where('card_id', $item->card_id)
            ->where('parallel_id', $item->parallel_id)
            ->where('grader', $item->grader)
            ->where('grade', $item->grade)
            ->where('sold_on', '>=', now()->subDays(180))
            ->avg('price');

        if ($average === null) {
            return back()->withErrors(['comps' => 'No comps from the last 6 months match this card’s parallel and grade.']);
        }

        $item->update(['estimated_value' => (int) round((float) $average), 'value_updated_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Value set to $'.Money::toDollars((int) round((float) $average)).'.']);

        return back();
    }

    /**
     * Delete a comp.
     */
    public function destroy(PriceComp $comp): RedirectResponse
    {
        $comp->delete();

        return back();
    }
}
