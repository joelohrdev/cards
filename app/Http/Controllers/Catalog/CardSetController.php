<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\ResolveCard;
use App\Enums\ItemStatus;
use App\Enums\Sport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CardSetRequest;
use App\Http\Resources\CardSetResource;
use App\Models\Card;
use App\Models\CardSet;
use App\Models\CollectionItem;
use App\Models\Manufacturer;
use App\Models\WantListEntry;
use App\Support\ChecklistParser;
use App\Support\FormOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CardSetController extends Controller
{
    /**
     * Browse the shared set catalog with the collection's completion progress.
     */
    public function index(Request $request): Response
    {
        $query = trim((string) $request->string('q'));

        $sets = CardSet::query()
            ->with('manufacturer')
            ->withCount('cards')
            ->withCount(['cards as owned_count' => fn (Builder $cards) => $cards->whereHas(
                'collectionItems',
                fn (Builder $items) => $items->whereIn('status', ItemStatus::inHand()),
            )])
            ->when($query !== '', function (Builder $sets) use ($query): void {
                foreach (array_filter(explode(' ', $query)) as $word) {
                    $sets->where(fn (Builder $set) => $set->whereLike('name', "%{$word}%")
                        ->orWhere('year', $word)
                        ->orWhere('season', $word)
                        ->orWhereHas('manufacturer', fn (Builder $manufacturer) => $manufacturer->whereLike('name', "%{$word}%")));
                }
            })
            ->when($request->string('sport')->toString(), fn (Builder $sets, string $sport) => $sets->where('sport', $sport))
            ->when($request->boolean('mine'), fn (Builder $sets) => $sets->whereHas(
                'cards.collectionItems',
                fn (Builder $items) => $items,
            ))
            ->orderByDesc('year')
            ->orderBy('name')
            ->paginate(40)
            ->withQueryString()
            ->through(fn (CardSet $set): array => [
                ...CardSetResource::make($set)->resolve(),
                'cards_count' => $set->cards_count,
                'owned_count' => $set->owned_count,
            ]);

        return Inertia::render('catalog/sets/index', [
            'sets' => $sets,
            'query' => $query,
            'sport' => $request->string('sport')->toString() ?: null,
            'mine' => $request->boolean('mine'),
            'sports' => Sport::options(),
        ]);
    }

    /**
     * Show the new-set form.
     */
    public function create(): Response
    {
        return Inertia::render('catalog/sets/create', [
            'sports' => Sport::options(),
            'manufacturers' => Manufacturer::query()->orderBy('name')->pluck('name'),
        ]);
    }

    /**
     * Create a set, optionally with a pasted checklist.
     */
    public function store(CardSetRequest $request, ChecklistParser $parser, ResolveCard $resolveCard): RedirectResponse
    {
        $data = $request->validated();

        $set = DB::transaction(function () use ($data, $parser, $resolveCard): CardSet {
            $set = CardSet::query()->firstOrCreate([
                'sport' => $data['sport'],
                'year' => $data['year'],
                'name' => trim($data['name']),
                'manufacturer_id' => filled($data['manufacturer'] ?? null)
                    ? Manufacturer::query()->firstOrCreate(['name' => trim($data['manufacturer'])])->id
                    : null,
            ], collect($data)->only(['season', 'card_count', 'notes'])->all());

            foreach ($parser->parse($data['checklist'] ?? '') as $card) {
                $resolveCard->handle($set, $card);
            }

            return $set;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Created {$set->full_name}."]);

        return to_route('sets.show', $set);
    }

    /**
     * Show a set's checklist with ownership, ready for checklist-mode entry.
     */
    public function show(Request $request, CardSet $set): Response
    {
        $set->load(['manufacturer', 'parallels' => fn ($parallels) => $parallels->orderBy('name')]);

        $owned = CollectionItem::query()
            ->inHand()
            ->whereIn('card_id', $set->cards()->select('id'))
            ->with('parallel')
            ->get()
            ->groupBy('card_id');

        $cards = $set->cards()
            ->with(['players', 'team'])
            ->orderBy('sort_order')
            ->orderBy('number')
            ->get()
            ->map(fn (Card $card): array => [
                'id' => $card->id,
                'number' => $card->number,
                'name' => $card->name,
                'subset' => $card->subset,
                'team' => $card->team?->name,
                'players' => $card->players->pluck('name')->all(),
                'is_rookie' => $card->is_rookie,
                'is_autograph' => $card->is_autograph,
                'is_memorabilia' => $card->is_memorabilia,
                'is_short_print' => $card->is_short_print,
                'print_run' => $card->print_run,
                'owned' => $owned->get($card->id, collect())->map(fn (CollectionItem $item): array => [
                    'id' => $item->id,
                    'parallel' => $item->parallel?->name,
                    'condition' => $item->conditionLabel(),
                ])->values(),
                'wanted' => false,
            ]);

        $wanted = WantListEntry::query()->whereIn('card_id', $cards->pluck('id'))->pluck('card_id')->flip();

        return Inertia::render('catalog/sets/show', [
            'set' => [
                ...CardSetResource::make($set)->resolve(),
                'notes' => $set->notes,
                'parallels' => $set->parallels->map->only(['id', 'name', 'print_run'])->values(),
            ],
            'cards' => $cards->map(fn (array $card): array => [...$card, 'wanted' => $wanted->has($card['id'])])->values(),
            'options' => FormOptions::for(),
            'manufacturers' => Manufacturer::query()->orderBy('name')->pluck('name'),
        ]);
    }

    /**
     * Update a set's details.
     */
    public function update(CardSetRequest $request, CardSet $set): RedirectResponse
    {
        $data = $request->validated();

        $set->update([
            ...collect($data)->except(['manufacturer', 'checklist'])->all(),
            'manufacturer_id' => filled($data['manufacturer'] ?? null)
                ? Manufacturer::query()->firstOrCreate(['name' => trim($data['manufacturer'])])->id
                : null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Set updated.']);

        return to_route('sets.show', $set);
    }

    /**
     * Delete a set nobody owns cards from.
     */
    public function destroy(CardSet $set): RedirectResponse
    {
        if (CollectionItem::query()->whereIn('card_id', $set->cards()->select('id'))->exists()) {
            return back()->withErrors(['set' => 'Cards from this set are in a collection, so it can’t be deleted.']);
        }

        $set->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Set deleted.']);

        return to_route('sets.index');
    }
}
