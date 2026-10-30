<?php

namespace App\Http\Controllers\Data;

use App\Enums\ItemStatus;
use App\Http\Controllers\Controller;
use App\Models\CollectionItem;
use App\Models\ItemImage;
use App\Models\Player;
use App\Models\ShareLink;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public, read-only showcase behind a share link. No sign-in required.
 *
 * Only cards still in hand are shown, limited to the link's tag when it has one. Purchase
 * details, notes, cert numbers, storage locations, tags and status never leave the server;
 * estimated values are included only when the owner turned "show values" on.
 */
class ShowcaseController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const array SORTS = [
        'value_desc' => 'Most valuable',
        'year_desc' => 'Newest first',
        'year_asc' => 'Oldest first',
        'set' => 'Set & number',
        'name' => 'Player name',
    ];

    public function show(Request $request, string $token): Response
    {
        $link = ShareLink::query()->with('user')->where('token', $token)->first();

        abort_if($link === null || $link->isExpired(), 404);

        $sorts = collect(self::SORTS)->when(! $link->show_values, fn ($sorts) => $sorts->except('value_desc'));
        $sort = $sorts->has((string) $request->string('sort')) ? (string) $request->string('sort') : $sorts->keys()->first();
        $search = Str::limit(trim((string) $request->string('q')), 100, '');

        $query = $this->baseQuery($link);
        $totals = (clone $query)->toBase()->selectRaw('count(*) as item_count, coalesce(sum(collection_items.estimated_value), 0) as total_value')->first();

        $this->applySearch($query, $search);
        $this->applySort($query, $sort);

        $items = $query
            ->with(['card.cardSet.manufacturer', 'card.team', 'card.players', 'parallel', 'images'])
            ->paginate(48)
            ->withQueryString()
            ->through(fn (CollectionItem $item): array => $this->present($item, $link->show_values));

        return Inertia::render('public/showcase', [
            'showcase' => [
                'title' => $link->title,
                'description' => $link->description,
                'show_values' => $link->show_values,
                'card_count' => (int) ($totals->item_count ?? 0),
                'total_value' => $link->show_values ? (int) ($totals->total_value ?? 0) : null,
                'expires_at' => $link->expires_at?->toDateString(),
            ],
            'items' => $items,
            'query' => $search,
            'sort' => $sort,
            'sorts' => $sorts->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    /**
     * @return Builder<CollectionItem>
     */
    private function baseQuery(ShareLink $link): Builder
    {
        return CollectionItem::query()
            ->select('collection_items.*')
            ->join('cards', 'cards.id', '=', 'collection_items.card_id')
            ->join('card_sets', 'card_sets.id', '=', 'cards.card_set_id')
            ->leftJoin('parallels', 'parallels.id', '=', 'collection_items.parallel_id')
            ->whereIn('collection_items.status', ItemStatus::inHand())
            ->when($link->tag_id, fn (Builder $query, int $tagId) => $query->whereExists(fn (QueryBuilder $sub) => $sub->selectRaw('1')
                ->from('collection_item_tag')
                ->whereColumn('collection_item_tag.collection_item_id', 'collection_items.id')
                ->where('collection_item_tag.tag_id', $tagId)));
    }

    /**
     * Every word must match a public field: player, card name, number, set, brand, team,
     * parallel, subset, or a year. Private fields (notes, certs, tags) are never searched,
     * so the search box can't be used to probe them.
     *
     * @param  Builder<CollectionItem>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $terms = array_slice(array_filter(preg_split('/\s+/', Str::lower($search)) ?: []), 0, 8);

        foreach ($terms as $term) {
            if (preg_match('/^(?:18|19|20)\d{2}$/', $term)) {
                $query->where('card_sets.year', (int) $term);

                continue;
            }

            $term = ltrim($term, '#');
            $like = '%'.$term.'%';

            $query->where(fn (Builder $match) => $match
                ->where('cards.number', $term)
                ->orWhereLike('cards.name', $like)
                ->orWhereLike('cards.subset', $like)
                ->orWhereLike('card_sets.name', $like)
                ->orWhereLike('parallels.name', $like)
                ->orWhereExists(fn (QueryBuilder $sub) => $sub->selectRaw('1')
                    ->from('manufacturers')
                    ->whereColumn('manufacturers.id', 'card_sets.manufacturer_id')
                    ->whereLike('manufacturers.name', $like))
                ->orWhereExists(fn (QueryBuilder $sub) => $sub->selectRaw('1')
                    ->from('teams')
                    ->whereColumn('teams.id', 'cards.team_id')
                    ->whereLike('teams.name', $like))
                ->orWhereExists(fn (QueryBuilder $sub) => $sub->selectRaw('1')
                    ->from('card_player')
                    ->join('players', 'players.id', '=', 'card_player.player_id')
                    ->whereColumn('card_player.card_id', 'cards.id')
                    ->whereLike('players.name', $like)));
        }
    }

    /**
     * @param  Builder<CollectionItem>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'value_desc' => $query->orderByRaw('collection_items.estimated_value IS NULL')->orderByDesc('collection_items.estimated_value'),
            'year_asc' => $query->orderBy('card_sets.year')->orderBy('card_sets.name')->orderBy('cards.sort_order'),
            'set' => $query->orderBy('card_sets.name')->orderBy('card_sets.year')->orderBy('cards.sort_order')->orderBy('cards.number'),
            'name' => $query->orderBy('cards.name'),
            default => $query->orderByDesc('card_sets.year')->orderBy('card_sets.name')->orderBy('cards.sort_order'),
        };

        $query->orderBy('collection_items.id');
    }

    /**
     * Only what a visitor should see. Record ids are left out on purpose.
     *
     * @return array<string, mixed>
     */
    private function present(CollectionItem $item, bool $showValues): array
    {
        $card = $item->card;
        $set = $card->cardSet;

        return [
            'key' => substr(hash('sha256', $item->id.'|'.$item->created_at->timestamp), 0, 16),
            'card' => [
                'number' => $card->number,
                'name' => $card->name,
                'subset' => $card->subset,
                'team' => $card->team?->name,
                'players' => $card->players->map(fn (Player $player): string => $player->name)->values(),
                'is_rookie' => $card->is_rookie,
                'is_autograph' => $card->is_autograph,
                'is_memorabilia' => $card->is_memorabilia,
                'is_short_print' => $card->is_short_print,
                'set' => [
                    'full_name' => $set->full_name,
                    'name' => $set->name,
                    'year' => $set->year,
                    'sport_label' => $set->sport->label(),
                    'manufacturer' => $set->manufacturer?->name,
                ],
            ],
            'parallel' => $item->parallel ? ['name' => $item->parallel->name, 'print_run' => $item->parallel->print_run] : null,
            'print_run' => $item->parallel->print_run ?? $card->print_run,
            'serial_number' => $item->serial_number,
            'is_graded' => $item->is_graded,
            'grader' => $item->grader?->value,
            'grade' => $item->grade,
            'condition_label' => $item->conditionLabel(),
            'estimated_value' => $showValues ? $item->estimated_value : null,
            'image' => $item->images->first()?->url,
            'images' => $item->images->map(fn (ItemImage $image): array => ['url' => $image->url, 'side' => $image->side->value])->values(),
        ];
    }
}
