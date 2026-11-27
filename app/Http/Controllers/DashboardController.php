<?php

namespace App\Http\Controllers;

use App\Enums\GradingStatus;
use App\Enums\ItemStatus;
use App\Enums\ListingStatus;
use App\Enums\Sport;
use App\Http\Resources\CollectionItemResource;
use App\Models\CardSet;
use App\Models\CollectionItem;
use App\Models\EbayListing;
use App\Models\GradingSubmission;
use App\Models\Import;
use App\Models\Invitation;
use App\Models\Sale;
use App\Models\StorageLocation;
use App\Models\Tag;
use App\Models\User;
use App\Models\ValueSnapshot;
use App\Models\WantListEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The collection at a glance.
     */
    public function __invoke(Request $request): Response
    {
        $totals = CollectionItem::query()->inHand()->toBase()->selectRaw(
            'count(*) as item_count,
            coalesce(sum(estimated_value), 0) as total_value,
            coalesce(sum(purchase_price), 0) as total_cost,
            coalesce(sum(case when estimated_value is not null and purchase_price is not null then estimated_value - purchase_price else 0 end), 0) as unrealized,
            sum(case when estimated_value is null then 1 else 0 end) as unvalued,
            sum(case when is_graded then 1 else 0 end) as graded'
        )->first();

        $sales = Sale::query()
            ->join('collection_items', 'collection_items.id', '=', 'sales.collection_item_id')
            ->whereYear('sales.sold_on', now()->year)
            ->toBase()
            ->selectRaw('count(*) as sale_count, coalesce(sum(sales.sale_price - sales.fees - sales.shipping_cost), 0) as net, coalesce(sum(collection_items.purchase_price), 0) as cost')
            ->first();

        $bySport = CollectionItem::query()->inHand()
            ->join('cards', 'cards.id', '=', 'collection_items.card_id')
            ->join('card_sets', 'card_sets.id', '=', 'cards.card_set_id')
            ->groupBy('card_sets.sport')
            ->toBase()
            ->selectRaw('card_sets.sport as sport, count(*) as item_count, coalesce(sum(collection_items.estimated_value), 0) as total_value')
            ->orderByDesc('total_value')
            ->get()
            ->map(fn (object $row): array => [
                'sport' => Sport::from($row->sport)->label(),
                'value' => $row->sport,
                'count' => (int) $row->item_count,
                'total' => (int) $row->total_value,
            ]);

        $byDecade = CollectionItem::query()->inHand()
            ->join('cards', 'cards.id', '=', 'collection_items.card_id')
            ->join('card_sets', 'card_sets.id', '=', 'cards.card_set_id')
            ->groupBy(DB::raw('card_sets.year - (card_sets.year % 10)'))
            ->toBase()
            ->selectRaw('card_sets.year - (card_sets.year % 10) as decade, count(*) as item_count')
            ->orderBy('decade')
            ->get()
            ->map(fn (object $row): array => ['decade' => (int) $row->decade, 'count' => (int) $row->item_count]);

        $history = ValueSnapshot::query()
            ->where('captured_on', '>=', now()->subYear())
            ->orderBy('captured_on')
            ->get()
            ->map(fn (ValueSnapshot $snapshot): array => [
                'date' => $snapshot->captured_on->toDateString(),
                'value' => $snapshot->total_value,
                'cost' => $snapshot->total_cost,
            ])
            ->reject(fn (array $point): bool => $point['date'] === today()->toDateString())
            ->push(['date' => today()->toDateString(), 'value' => (int) $totals->total_value, 'cost' => (int) $totals->total_cost])
            ->values();

        $resource = fn (CollectionItem $item): array => CollectionItemResource::make($item)->resolve();

        $setProgress = CardSet::query()
            ->with('manufacturer')
            ->withCount('cards')
            ->withCount(['cards as owned_count' => fn (Builder $cards) => $cards->whereHas(
                'collectionItems',
                fn (Builder $items) => $items->whereIn('status', ItemStatus::inHand()),
            )])
            ->whereHas('cards.collectionItems', fn (Builder $items) => $items)
            ->get()
            ->filter(fn (CardSet $set): bool => $set->cards_count > 1)
            ->sortByDesc(fn (CardSet $set): float => $set->owned_count / max($set->card_count ?? $set->cards_count, 1))
            ->take(5)
            ->map(fn (CardSet $set): array => [
                'id' => $set->id,
                'name' => $set->full_name,
                'owned' => $set->owned_count,
                'total' => max($set->card_count ?? 0, $set->cards_count),
            ])
            ->values();

        return Inertia::render('dashboard', [
            'awaitingGrading' => $this->awaitingGrading(),
            'totals' => [
                'count' => (int) $totals->item_count,
                'value' => (int) $totals->total_value,
                'cost' => (int) $totals->total_cost,
                'unrealized' => (int) $totals->unrealized,
                'unvalued' => (int) $totals->unvalued,
                'graded' => (int) $totals->graded,
            ],
            'sales' => [
                'count' => (int) $sales->sale_count,
                'net' => (int) $sales->net,
                'profit' => (int) $sales->net - (int) $sales->cost,
                'year' => now()->year,
            ],
            'bySport' => $bySport,
            'byDecade' => $byDecade,
            'history' => $history,
            'topItems' => CollectionItem::query()->inHand()->whereNotNull('estimated_value')
                ->with(CollectionItemResource::LIST_RELATIONS)
                ->orderByDesc('estimated_value')
                ->limit(6)
                ->get()
                ->map($resource),
            'recentItems' => CollectionItem::query()
                ->with(CollectionItemResource::LIST_RELATIONS)
                ->latest()
                ->latest('id')
                ->limit(6)
                ->get()
                ->map($resource),
            'setProgress' => $setProgress,
            'setup' => CollectionItem::query()->exists() ? null : $this->setupSteps($request->user()),
            'activity' => [
                'grading' => GradingSubmission::query()->where('status', '!=', GradingStatus::Completed)->count(),
                'listings' => EbayListing::query()->where('status', ListingStatus::Active)->count(),
                'wants' => WantListEntry::query()->count(),
            ],
        ]);
    }

    /**
     * Cards in open grading submissions, those still to be sent first, then those out the
     * longest. Counts cover every card; the list shows the first few.
     *
     * @return array{going_to_send: int, at_grader: int, items: list<array<string, mixed>>}
     */
    private function awaitingGrading(): array
    {
        $open = fn (Builder|BelongsToMany $submissions) => $submissions->where('grading_submissions.status', '!=', GradingStatus::Completed->value);

        $rows = CollectionItem::query()
            ->whereHas('gradingSubmissions', $open)
            ->with([...CollectionItemResource::LIST_RELATIONS, 'gradingSubmissions' => $open])
            ->get()
            ->map(fn (CollectionItem $item): array => ['item' => $item, 'submission' => $item->gradingSubmissions->first()])
            ->filter(fn (array $row): bool => $row['submission'] instanceof GradingSubmission)
            ->sortBy([
                fn (array $a, array $b): int => $a['submission']->isWithGrader() <=> $b['submission']->isWithGrader(),
                fn (array $a, array $b): int => $a['submission']->submitted_on <=> $b['submission']->submitted_on,
                fn (array $a, array $b): int => $a['item']->id <=> $b['item']->id,
            ]);

        [$atGrader, $goingToSend] = $rows->partition(fn (array $row): bool => $row['submission']->isWithGrader());

        return [
            'going_to_send' => $goingToSend->count(),
            'at_grader' => $atGrader->count(),
            'items' => array_values($rows->take(8)->map(fn (array $row): array => [
                'item' => CollectionItemResource::make($row['item'])->resolve(),
                'submission' => [
                    'id' => $row['submission']->id,
                    'grader' => $row['submission']->grader->value,
                    'status' => $row['submission']->status->value,
                    'status_label' => $row['submission']->status->label(),
                    'days_out' => $row['submission']->progress()['days_out'],
                ],
            ])->all()),
        ];
    }

    /**
     * What to set up before the first card goes in, and which steps are already done.
     * Inviting users only applies to admins, since only they can send invitations.
     *
     * @return list<array{key: string, done: bool}>
     */
    private function setupSteps(User $user): array
    {
        $steps = [
            'users' => User::query()->count() > 1 || Invitation::query()->open()->exists(),
            'storage' => StorageLocation::query()->exists(),
            'tags' => Tag::query()->exists(),
            'sets' => CardSet::query()->exists(),
            'import' => Import::query()->exists(),
            'ebay' => $user->ebayAccount()->exists(),
        ];

        if (! $user->isAdmin()) {
            unset($steps['users']);
        }

        return array_map(
            fn (string $key, bool $done): array => ['key' => $key, 'done' => $done],
            array_keys($steps),
            array_values($steps),
        );
    }
}
