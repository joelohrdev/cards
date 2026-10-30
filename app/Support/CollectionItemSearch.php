<?php

namespace App\Support;

use App\Enums\Grader;
use App\Enums\ItemStatus;
use App\Models\CollectionItem;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Turns a free-text query plus structured filters into a collection query.
 *
 * The free text understands collector shorthand: years ("1986", "2023-24"), card numbers ("#23"),
 * grades ("psa 10", "bgs 9.5"), print runs ("/99"), and flags ("rc", "auto", "relic", "sp",
 * "graded", "raw"). Everything else must match a player, set, brand, team, parallel, subset,
 * card number, cert number, tag, or note.
 */
class CollectionItemSearch
{
    /**
     * Keyword flags and the card column each one requires.
     *
     * @var array<string, string>
     */
    private const array FLAG_WORDS = [
        'rc' => 'is_rookie',
        'rookie' => 'is_rookie',
        'rookies' => 'is_rookie',
        'auto' => 'is_autograph',
        'autos' => 'is_autograph',
        'autograph' => 'is_autograph',
        'autographed' => 'is_autograph',
        'relic' => 'is_memorabilia',
        'relics' => 'is_memorabilia',
        'patch' => 'is_memorabilia',
        'jersey' => 'is_memorabilia',
        'mem' => 'is_memorabilia',
        'sp' => 'is_short_print',
        'ssp' => 'is_short_print',
    ];

    public const array SORTS = [
        'recent' => 'Recently added',
        'value_desc' => 'Value: high to low',
        'value_asc' => 'Value: low to high',
        'year_desc' => 'Year: newest',
        'year_asc' => 'Year: oldest',
        'set' => 'Set & number',
        'name' => 'Player / card name',
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private string $query = '',
        private array $filters = [],
    ) {}

    /**
     * @return Builder<CollectionItem>
     */
    public function builder(): Builder
    {
        $builder = CollectionItem::query()
            ->select('collection_items.*')
            ->join('cards', 'cards.id', '=', 'collection_items.card_id')
            ->join('card_sets', 'card_sets.id', '=', 'cards.card_set_id')
            ->leftJoin('parallels', 'parallels.id', '=', 'collection_items.parallel_id');

        $this->applyFreeText($builder);
        $this->applyFilters($builder);
        $this->applySort($builder);

        return $builder;
    }

    /**
     * Break the free text into recognised tokens and plain terms.
     *
     * @return array{years: list<int>, seasons: list<string>, numbers: list<string>, grades: list<array{grader: Grader|null, grade: string|null}>, print_runs: list<int>, flags: list<string>, graded: bool|null, terms: list<string>}
     */
    public function parse(): array
    {
        $parsed = [
            'years' => [],
            'seasons' => [],
            'numbers' => [],
            'grades' => [],
            'print_runs' => [],
            'flags' => [],
            'graded' => null,
            'terms' => [],
        ];

        $tokens = array_values(array_filter(preg_split('/\s+/', Str::lower(trim($this->query))) ?: []));
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];
            $grader = Grader::tryFrom(Str::upper($token));

            if ($grader !== null && $grader !== Grader::Other) {
                $next = $tokens[$index + 1] ?? null;
                $grade = $next !== null && preg_match('/^(10|[1-9](\.5)?|auth(entic)?)$/', $next) ? $next : null;
                $parsed['grades'][] = ['grader' => $grader, 'grade' => $grade];

                if ($grade !== null) {
                    $index++;
                }

                continue;
            }

            match (true) {
                (bool) preg_match('/^(18|19|20)\d{2}$/', $token) => $parsed['years'][] = (int) $token,
                (bool) preg_match('/^((18|19|20)\d{2})[-\/](\d{2})$/', $token) => $parsed['seasons'][] = $token,
                (bool) preg_match('/^#(.+)$/', $token, $match) => $parsed['numbers'][] = $match[1],
                (bool) preg_match('/^\/(\d+)$/', $token, $match) => $parsed['print_runs'][] = (int) $match[1],
                array_key_exists($token, self::FLAG_WORDS) => $parsed['flags'][] = self::FLAG_WORDS[$token],
                $token === 'graded', $token === 'slabbed' => $parsed['graded'] = true,
                $token === 'raw', $token === 'ungraded' => $parsed['graded'] = false,
                default => $parsed['terms'][] = $token,
            };
        }

        return $parsed;
    }

    /**
     * @param  Builder<CollectionItem>  $builder
     */
    private function applyFreeText(Builder $builder): void
    {
        $parsed = $this->parse();

        foreach ($parsed['years'] as $year) {
            $builder->where('card_sets.year', $year);
        }

        foreach ($parsed['seasons'] as $season) {
            $builder->where(function (Builder $query) use ($season): void {
                $query->where('card_sets.season', $season)
                    ->orWhere('card_sets.year', (int) substr($season, 0, 4));
            });
        }

        foreach ($parsed['numbers'] as $number) {
            $builder->where('cards.number', $number);
        }

        foreach ($parsed['grades'] as $grade) {
            $builder->where('collection_items.is_graded', true)
                ->where('collection_items.grader', $grade['grader']);

            if ($grade['grade'] !== null) {
                $builder->where('collection_items.grade', str_starts_with($grade['grade'], 'auth') ? 'Authentic' : $grade['grade']);
            }
        }

        foreach ($parsed['print_runs'] as $printRun) {
            $builder->where(function (Builder $query) use ($printRun): void {
                $query->where('parallels.print_run', $printRun)->orWhere('cards.print_run', $printRun);
            });
        }

        foreach (array_unique($parsed['flags']) as $flag) {
            $builder->where('cards.'.$flag, true);
        }

        if ($parsed['graded'] !== null) {
            $builder->where('collection_items.is_graded', $parsed['graded']);
        }

        foreach ($parsed['terms'] as $term) {
            $this->applyTerm($builder, $term);
        }
    }

    /**
     * Require a single word to match at least one descriptive field.
     *
     * @param  Builder<CollectionItem>  $builder
     */
    private function applyTerm(Builder $builder, string $term): void
    {
        $like = '%'.$term.'%';

        $builder->where(function (Builder $query) use ($term, $like): void {
            $query->where('cards.number', $term)
                ->orWhereLike('cards.name', $like)
                ->orWhereLike('cards.subset', $like)
                ->orWhereLike('card_sets.name', $like)
                ->orWhereLike('parallels.name', $like)
                ->orWhereLike('collection_items.notes', $like)
                ->orWhere('collection_items.cert_number', $term)
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
                    ->whereLike('players.name', $like))
                ->orWhereExists(fn (QueryBuilder $sub) => $sub->selectRaw('1')
                    ->from('collection_item_tag')
                    ->join('tags', 'tags.id', '=', 'collection_item_tag.tag_id')
                    ->whereColumn('collection_item_tag.collection_item_id', 'collection_items.id')
                    ->whereLike('tags.name', $like));
        });
    }

    /**
     * @param  Builder<CollectionItem>  $builder
     */
    private function applyFilters(Builder $builder): void
    {
        $filters = $this->filters;

        $statuses = array_filter((array) ($filters['status'] ?? []));

        if ($statuses === []) {
            $builder->whereIn('collection_items.status', ItemStatus::inHand());
        } elseif (! in_array('all', $statuses, true)) {
            $builder->whereIn('collection_items.status', $statuses);
        }

        $builder
            ->when($filters['sport'] ?? null, fn (Builder $query, $sport) => $query->whereIn('card_sets.sport', (array) $sport))
            ->when($filters['year_from'] ?? null, fn (Builder $query, $year) => $query->where('card_sets.year', '>=', (int) $year))
            ->when($filters['year_to'] ?? null, fn (Builder $query, $year) => $query->where('card_sets.year', '<=', (int) $year))
            ->when($filters['set'] ?? null, fn (Builder $query, $set) => $query->where('card_sets.id', (int) $set))
            ->when($filters['player'] ?? null, fn (Builder $query, $player) => $query->whereExists(fn (QueryBuilder $sub) => $sub->selectRaw('1')
                ->from('card_player')
                ->whereColumn('card_player.card_id', 'cards.id')
                ->where('card_player.player_id', (int) $player)))
            ->when($filters['team'] ?? null, fn (Builder $query, $team) => $query->where('cards.team_id', (int) $team))
            ->when($filters['tag'] ?? null, fn (Builder $query, $tags) => $query->whereHas('tags', fn (Builder $tagQuery) => $tagQuery->whereIn('tags.id', array_map('intval', (array) $tags))))
            ->when($filters['location'] ?? null, fn (Builder $query, $location) => $location === 'none'
                ? $query->whereNull('collection_items.storage_location_id')
                : $query->where('collection_items.storage_location_id', (int) $location))
            ->when($filters['grader'] ?? null, fn (Builder $query, $grader) => $query->where('collection_items.grader', $grader))
            ->when(($filters['graded'] ?? null) === 'graded', fn (Builder $query) => $query->where('collection_items.is_graded', true))
            ->when(($filters['graded'] ?? null) === 'raw', fn (Builder $query) => $query->where('collection_items.is_graded', false))
            ->when($filters['rookie'] ?? null, fn (Builder $query) => $query->where('cards.is_rookie', true))
            ->when($filters['autograph'] ?? null, fn (Builder $query) => $query->where('cards.is_autograph', true))
            ->when($filters['memorabilia'] ?? null, fn (Builder $query) => $query->where('cards.is_memorabilia', true))
            ->when($filters['numbered'] ?? null, fn (Builder $query) => $query->where(fn (Builder $numbered) => $numbered
                ->whereNotNull('parallels.print_run')
                ->orWhereNotNull('cards.print_run')))
            ->when(($filters['min_value'] ?? null) !== null && $filters['min_value'] !== '', fn (Builder $query) => $query->where('collection_items.estimated_value', '>=', Money::toCents($filters['min_value'])))
            ->when(($filters['max_value'] ?? null) !== null && $filters['max_value'] !== '', fn (Builder $query) => $query->where('collection_items.estimated_value', '<=', Money::toCents($filters['max_value'])))
            ->when(($filters['has_image'] ?? null) === 'no', fn (Builder $query) => $query->doesntHave('images'))
            ->when(($filters['has_value'] ?? null) === 'no', fn (Builder $query) => $query->whereNull('collection_items.estimated_value'));
    }

    /**
     * @param  Builder<CollectionItem>  $builder
     */
    private function applySort(Builder $builder): void
    {
        match ($this->filters['sort'] ?? 'recent') {
            'value_desc' => $builder->orderByRaw('collection_items.estimated_value IS NULL')->orderByDesc('collection_items.estimated_value'),
            'value_asc' => $builder->orderByRaw('collection_items.estimated_value IS NULL')->orderBy('collection_items.estimated_value'),
            'year_desc' => $builder->orderByDesc('card_sets.year')->orderBy('card_sets.name')->orderBy('cards.sort_order'),
            'year_asc' => $builder->orderBy('card_sets.year')->orderBy('card_sets.name')->orderBy('cards.sort_order'),
            'set' => $builder->orderBy('card_sets.name')->orderBy('card_sets.year')->orderBy('cards.sort_order')->orderBy('cards.number'),
            'name' => $builder->orderBy('cards.name'),
            default => $builder->orderByDesc('collection_items.created_at'),
        };

        $builder->orderByDesc('collection_items.id');
    }
}
