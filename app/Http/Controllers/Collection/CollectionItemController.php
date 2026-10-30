<?php

namespace App\Http\Controllers\Collection;

use App\Actions\Collection\SaveCollectionItem;
use App\Enums\ImageSide;
use App\Http\Controllers\Controller;
use App\Http\Requests\Collection\CollectionItemRequest;
use App\Http\Resources\CardResource;
use App\Http\Resources\CollectionItemResource;
use App\Models\Card;
use App\Models\CardSet;
use App\Models\CollectionItem;
use App\Models\EbayListing;
use App\Models\GradingSubmission;
use App\Models\ItemImage;
use App\Models\PriceComp;
use App\Support\CollectionItemSearch;
use App\Support\FormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CollectionItemController extends Controller
{
    /**
     * Filters accepted by the collection browser.
     *
     * @var list<string>
     */
    public const array FILTER_KEYS = [
        'sport', 'year_from', 'year_to', 'set', 'player', 'team', 'tag', 'location', 'status', 'grader',
        'graded', 'rookie', 'autograph', 'memorabilia', 'numbered', 'min_value', 'max_value', 'has_image',
        'has_value', 'sort',
    ];

    /**
     * Browse and search the collection.
     */
    public function index(Request $request): Response
    {
        $query = (string) $request->string('q');
        $filters = array_filter($request->only(self::FILTER_KEYS), fn ($value): bool => $value !== null && $value !== '' && $value !== []);
        $search = new CollectionItemSearch($query, $filters);
        $builder = $search->builder();

        $totals = (clone $builder)->toBase()->reorder()->selectRaw(
            'count(*) as item_count, coalesce(sum(collection_items.estimated_value), 0) as total_value, coalesce(sum(collection_items.purchase_price), 0) as total_cost'
        )->first();

        $items = $builder
            ->with(CollectionItemResource::LIST_RELATIONS)
            ->paginate(48)
            ->withQueryString()
            ->through(fn (CollectionItem $item): array => CollectionItemResource::make($item)->resolve());

        $ownedSets = CardSet::query()
            ->with('manufacturer')
            ->whereIn('id', CollectionItem::query()->join('cards', 'cards.id', '=', 'collection_items.card_id')->select('cards.card_set_id'))
            ->orderByDesc('year')
            ->orderBy('name')
            ->get()
            ->map(fn (CardSet $set): array => ['value' => (string) $set->id, 'label' => $set->full_name]);

        return Inertia::render('collection/index', [
            'items' => $items,
            'query' => $query,
            'filters' => (object) $filters,
            'parsed' => $search->parse(),
            'totals' => [
                'count' => (int) ($totals->item_count ?? 0),
                'value' => (int) ($totals->total_value ?? 0),
                'cost' => (int) ($totals->total_cost ?? 0),
            ],
            'sorts' => collect(CollectionItemSearch::SORTS)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values(),
            'sets' => $ownedSets,
            'options' => FormOptions::for(),
        ]);
    }

    /**
     * Show the add-a-card form.
     */
    public function create(Request $request): Response
    {
        $prefillCard = null;

        if ($request->filled('card')) {
            $card = Card::query()->with(['cardSet.manufacturer', 'cardSet.parallels', 'players', 'team'])->findOrFail($request->integer('card'));
            $prefillCard = [
                ...CardResource::make($card)->resolve(),
                'parallels' => $card->cardSet->parallels->sortBy('name')->map->only(['id', 'name', 'print_run'])->values(),
            ];
        }

        return Inertia::render('collection/create', [
            'options' => FormOptions::for(),
            'prefillCard' => $prefillCard,
        ]);
    }

    /**
     * Add one or more copies of a card to the collection.
     */
    public function store(CollectionItemRequest $request, SaveCollectionItem $saveCollectionItem): RedirectResponse
    {
        $data = $request->validated();
        $quantity = (int) ($data['quantity'] ?? 1);
        $item = null;

        for ($copy = 0; $copy < $quantity; $copy++) {
            $item = $saveCollectionItem->handle($request->user(), $data);
            $data['card_id'] = $item->card_id;
            $data['parallel_id'] = $item->parallel_id;

            foreach (['photo' => ImageSide::Front, 'back_photo' => ImageSide::Back] as $field => $side) {
                if ($request->hasFile($field)) {
                    $item->images()->create([
                        'path' => $request->file($field)->store('cards/'.$item->user_id, ItemImage::DISK),
                        'side' => $side,
                        'position' => $item->images()->count(),
                    ]);
                }
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $quantity > 1 ? "Added {$quantity} copies of {$item->title()}." : "Added {$item->title()}.",
        ]);

        if ($request->boolean('add_another')) {
            return to_route('collection.create');
        }

        return to_route('collection.show', $item);
    }

    /**
     * Show a single card in the collection.
     */
    public function show(Request $request, CollectionItem $item): Response
    {
        $item->load([...CollectionItemResource::LIST_RELATIONS, 'images', 'sale', 'card.cardSet.parallels']);

        $otherCopies = CollectionItem::query()
            ->where('card_id', $item->card_id)
            ->whereKeyNot($item->id)
            ->with(CollectionItemResource::LIST_RELATIONS)
            ->get()
            ->map(fn (CollectionItem $copy): array => CollectionItemResource::make($copy)->resolve());

        $comps = PriceComp::query()
            ->where('card_id', $item->card_id)
            ->with('parallel')
            ->latest('sold_on')
            ->limit(25)
            ->get()
            ->map(fn (PriceComp $comp): array => [
                'id' => $comp->id,
                'price' => $comp->price,
                'sold_on' => $comp->sold_on->toDateString(),
                'grader' => $comp->grader?->value,
                'grade' => $comp->grade,
                'parallel' => $comp->parallel?->name,
                'source' => $comp->source,
                'url' => $comp->url,
                'notes' => $comp->notes,
                'matches_item' => $comp->parallel_id === $item->parallel_id
                    && $comp->grader === $item->grader
                    && $comp->grade === $item->grade,
            ]);

        return Inertia::render('collection/show', [
            'item' => CollectionItemResource::make($item)->resolve(),
            'parallels' => $item->card->cardSet->parallels->map->only(['id', 'name', 'print_run'])->values(),
            'otherCopies' => $otherCopies,
            'comps' => $comps,
            'sale' => $item->sale ? [
                'id' => $item->sale->id,
                'platform' => $item->sale->platform->label(),
                'sold_on' => $item->sale->sold_on->toDateString(),
                'sale_price' => $item->sale->sale_price,
                'fees' => $item->sale->fees,
                'shipping_cost' => $item->sale->shipping_cost,
                'net' => $item->sale->netProceeds(),
                'buyer' => $item->sale->buyer,
            ] : null,
            'listings' => $item->ebayListings()->latest()->get()->map(fn (EbayListing $listing): array => [
                'id' => $listing->id,
                'title' => $listing->title,
                'price' => $listing->price,
                'status' => $listing->status->value,
                'url' => $listing->url(),
                'published_at' => $listing->published_at?->toIso8601String(),
                'error' => $listing->error,
            ]),
            'gradingSubmissions' => $item->gradingSubmissions()->latest('grading_submissions.created_at')->get()->map(fn (GradingSubmission $submission): array => [
                'id' => $submission->id,
                'grader' => $submission->grader->value,
                'status' => $submission->status->label(),
                'submitted_on' => $submission->submitted_on?->toDateString(),
                'resulting_grade' => $submission->getRelation('pivot')->resulting_grade,
            ]),
            'options' => FormOptions::for(),
        ]);
    }

    /**
     * Show the edit form.
     */
    public function edit(Request $request, CollectionItem $item): Response
    {
        $item->load([...CollectionItemResource::LIST_RELATIONS, 'card.cardSet.parallels']);

        return Inertia::render('collection/edit', [
            'item' => CollectionItemResource::make($item)->resolve(),
            'parallels' => $item->card->cardSet->parallels->map->only(['id', 'name', 'print_run'])->values(),
            'options' => FormOptions::for(),
        ]);
    }

    /**
     * Update a card in the collection.
     */
    public function update(CollectionItemRequest $request, CollectionItem $item, SaveCollectionItem $saveCollectionItem): RedirectResponse
    {
        $saveCollectionItem->handle($request->user(), $request->validated(), $item);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Card updated.']);

        return to_route('collection.show', $item);
    }

    /**
     * Remove a card from the collection.
     */
    public function destroy(CollectionItem $item): RedirectResponse
    {
        $paths = $item->images()->pluck('path')->all();
        $item->delete();
        Storage::disk(ItemImage::DISK)->delete($paths);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Card deleted.']);

        return to_route('collection.index');
    }
}
