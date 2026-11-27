<?php

namespace App\Support;

use App\Models\CardSet;
use App\Models\Manufacturer;
use App\Models\Parallel;
use App\Models\Player;
use App\Models\Tag;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Finds names that are already in the app more than once in slightly different spellings
 * ("Ken Griffey Jr" and "Ken Griffey Jr.", "eBay" and "ebay"), for the Tidy up names page.
 *
 * Records are grouped by a signature: the name's ExistingNames key plus whatever must also be
 * the same for two records to be the same thing (a player's sport, a set's year and brand, a
 * parallel's set).
 */
class DuplicateNames
{
    /**
     * Kinds of names, in the order they're best tidied: brands before sets (merging brands can
     * line up sets), sets before parallels (merging sets can line up parallels).
     *
     * @var array<string, string>
     */
    public const array TYPES = [
        'manufacturer' => 'Brands',
        'set' => 'Sets',
        'parallel' => 'Parallels',
        'player' => 'Players',
        'team' => 'Teams',
        'tag' => 'Tags',
        'subset' => 'Subsets',
        'purchase_source' => 'Bought from',
        'service_level' => 'Grading service levels',
        'buyer' => 'Buyers',
        'comp_source' => 'Price comp sources',
    ];

    /**
     * Text columns tidied by rewriting the value, not by merging records.
     *
     * @var list<string>
     */
    public const array TEXT_TYPES = ['subset', 'purchase_source', 'service_level', 'buyer', 'comp_source'];

    /**
     * Every group of near-duplicates, most used spelling first in each.
     *
     * @return list<array{type: string, label: string, signature: string, records: list<array{id: string, name: string, detail: string|null, uses: int}>}>
     */
    public function groups(): array
    {
        $groups = [];

        foreach (self::TYPES as $type => $label) {
            collect($this->records($type))
                ->groupBy('signature')
                ->filter(fn (Collection $records): bool => $records->count() > 1)
                ->each(function (Collection $records, string $signature) use (&$groups, $type, $label): void {
                    $groups[] = [
                        'type' => $type,
                        'label' => $label,
                        'signature' => $signature,
                        'records' => array_values($records
                            ->sortByDesc('uses')
                            ->map(fn (array $record): array => [
                                'id' => $record['id'],
                                'name' => $record['name'],
                                'detail' => $record['detail'],
                                'uses' => $record['uses'],
                            ])
                            ->all()),
                    ];
                });
        }

        return $groups;
    }

    /**
     * One kind of record with its signature, a detail line and how much it's used.
     *
     * @return list<array{id: string, name: string, detail: string|null, uses: int, signature: string}>
     */
    public function records(string $type): array
    {
        $key = ExistingNames::key(...);

        $records = match ($type) {
            'player' => Player::query()->withCount('cards')->get()->map(fn (Player $player): array => [
                'id' => (string) $player->id,
                'name' => $player->name,
                'detail' => $player->sport->label(),
                'uses' => $player->cards_count,
                'signature' => $player->sport->value.'|'.$key($player->name),
            ]),
            'team' => Team::query()->withCount('cards')->get()->map(fn (Team $team): array => [
                'id' => (string) $team->id,
                'name' => $team->name,
                'detail' => $team->sport->label(),
                'uses' => $team->cards_count,
                'signature' => $team->sport->value.'|'.$key($team->name),
            ]),
            'manufacturer' => Manufacturer::query()->withCount('cardSets')->get()->map(fn (Manufacturer $manufacturer): array => [
                'id' => (string) $manufacturer->id,
                'name' => $manufacturer->name,
                'detail' => null,
                'uses' => $manufacturer->card_sets_count,
                'signature' => $key($manufacturer->name),
            ]),
            'set' => CardSet::query()->with('manufacturer')->withCount('cards')->get()->map(fn (CardSet $set): array => [
                'id' => (string) $set->id,
                'name' => $set->full_name,
                'detail' => $set->sport->label(),
                'uses' => $set->cards_count,
                'signature' => implode('|', [$set->sport->value, $set->year, $key($set->manufacturer->name ?? ''), $key($set->name)]),
            ]),
            'parallel' => Parallel::query()->with('cardSet')->withCount('collectionItems')->get()->map(fn (Parallel $parallel): array => [
                'id' => (string) $parallel->id,
                'name' => $parallel->name,
                'detail' => $parallel->cardSet->full_name,
                'uses' => $parallel->collection_items_count,
                'signature' => $parallel->card_set_id.'|'.$key($parallel->name),
            ]),
            'tag' => Tag::query()->withCount('collectionItems')->get()->map(fn (Tag $tag): array => [
                'id' => (string) $tag->id,
                'name' => $tag->name,
                'detail' => null,
                'uses' => $tag->collection_items_count,
                'signature' => $key($tag->name),
            ]),
            default => collect($this->textValues($type)),
        };

        return array_values($records->all());
    }

    /**
     * @return list<array{id: string, name: string, detail: string|null, uses: int, signature: string}>
     */
    private function textValues(string $type): array
    {
        [$table, $column] = ExistingNames::FIELDS[$type];

        // Counted in PHP: MySQL's case-insensitive collation would fold "eBay" and "ebay" together.
        return array_values(DB::table($table)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->pluck($column)
            ->map(fn (mixed $value): string => (string) $value)
            ->countBy()
            ->map(fn (int $uses, string $value): array => [
                // Encoded so form input trimming can't change a value like "ebay ".
                'id' => base64_encode($value),
                'name' => $value,
                'detail' => null,
                'uses' => $uses,
                'signature' => ExistingNames::key($value),
            ])
            ->all());
    }
}
