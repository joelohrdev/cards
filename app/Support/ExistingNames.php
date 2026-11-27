<?php

namespace App\Support;

use App\Models\CardSet;
use App\Models\Parallel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Keeps names consistent: a name that matches one already in the app apart from capitalization,
 * punctuation, accents or spacing takes the existing spelling, so "ken griffey jr" is saved as
 * the existing "Ken Griffey Jr." and "ebay" as "eBay". Anything else is kept as entered, since a
 * looser match could pick the wrong player or team.
 *
 * Bound per request (or queued job), so a CSV import loads each list once and later rows see
 * names created by earlier ones.
 */
class ExistingNames
{
    /**
     * Name-like fields: [table, column, sport column or null].
     *
     * @var array<string, array{0: string, 1: string, 2: string|null}>
     */
    public const array FIELDS = [
        'tag' => ['tags', 'name', null],
        'player' => ['players', 'name', 'sport'],
        'team' => ['teams', 'name', 'sport'],
        'manufacturer' => ['manufacturers', 'name', null],
        'subset' => ['cards', 'subset', null],
        'purchase_source' => ['collection_items', 'purchase_source', null],
        'service_level' => ['grading_submissions', 'service_level', null],
        'buyer' => ['sales', 'buyer', null],
        'comp_source' => ['price_comps', 'source', null],
    ];

    /**
     * Known spellings per field, by key, most used first.
     *
     * @var array<string, array<string, list<array{name: string, sport: string|null}>>>
     */
    private array $known = [];

    /**
     * "Ronald Acuña Jr." and "ronald acuna jr" both become "ronaldacunajr"; "UpperDeck" and
     * "Upper Deck" both become "upperdeck".
     */
    public static function key(string $name): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii($name)));
    }

    /**
     * The existing spelling of a value in one of the FIELDS, preferring one from the same sport,
     * or the value tidied up (extra spaces removed) when it's new.
     */
    public function spelling(string $field, ?string $value, ?string $sport = null): ?string
    {
        if ($value === null || blank($value)) {
            return null;
        }

        $value = Str::squish($value);
        $key = self::key($value);

        if ($key === '') {
            return $value;
        }

        $candidates = $this->known($field)[$key] ?? [];
        $match = collect($candidates)->first(fn (array $known): bool => $sport !== null && $known['sport'] === $sport)
            ?? $candidates[0] ?? null;

        if ($match !== null) {
            return $match['name'];
        }

        $this->known[$field][$key][] = ['name' => $value, 'sport' => $sport];

        return $value;
    }

    /**
     * Player names from a "/"- or "&"-separated list, each in its existing spelling.
     *
     * @return list<string>
     */
    public function playerList(?string $names, ?string $sport = null): array
    {
        return array_values(collect(preg_split('/\s*(?:\/|&)\s*/', (string) $names) ?: [])
            ->map(fn (string $name): ?string => $this->spelling('player', $name, $sport))
            ->filter()
            ->unique(fn (string $name): string => self::key($name))
            ->all());
    }

    /**
     * A "/"-separated list of players, each in its existing spelling.
     */
    public function players(?string $names, ?string $sport = null): ?string
    {
        if ($names === null || blank($names)) {
            return $names;
        }

        $list = $this->playerList($names, $sport);

        return $list === [] ? $names : implode(' / ', $list);
    }

    /**
     * The existing set a card is from: same year, a name that matches apart from spelling, and
     * the same brand and sport when both sides have one. Without a year there's no telling which
     * year's set it is, so none is returned.
     */
    public function set(?string $name, ?int $year, ?string $manufacturer = null, ?string $sport = null): ?CardSet
    {
        if ($name === null || blank($name) || $year === null) {
            return null;
        }

        $nameKey = self::key($name);
        $manufacturerKey = blank($manufacturer) ? null : self::key((string) $manufacturer);

        $candidates = CardSet::query()
            ->with('manufacturer')
            ->where('year', $year)
            ->orderBy('id')
            ->get()
            ->filter(fn (CardSet $set): bool => self::key($set->name) === $nameKey
                && ($manufacturerKey === null || $set->manufacturer === null || self::key($set->manufacturer->name) === $manufacturerKey)
                && ($sport === null || $set->sport->value === $sport));

        return $candidates->first(fn (CardSet $set): bool => $manufacturerKey !== null && $set->manufacturer !== null)
            ?? $candidates->first();
    }

    /**
     * The existing spelling of a set name, preferring a set from the same brand.
     */
    public function setName(?string $name, ?string $manufacturer = null): ?string
    {
        if ($name === null || blank($name)) {
            return $name;
        }

        $manufacturerKey = blank($manufacturer) ? null : self::key((string) $manufacturer);
        $sameName = CardSet::query()
            ->with('manufacturer')
            ->orderBy('id')
            ->get()
            ->filter(fn (CardSet $set): bool => self::key($set->name) === self::key($name));

        $match = $sameName->first(fn (CardSet $set): bool => $manufacturerKey !== null
            && $set->manufacturer !== null
            && self::key($set->manufacturer->name) === $manufacturerKey)
            ?? $sameName->first();

        return $match->name ?? Str::squish($name);
    }

    /**
     * The set's existing parallel with this name apart from spelling, if any.
     */
    public function parallel(int $cardSetId, ?string $name): ?Parallel
    {
        if ($name === null || blank($name)) {
            return null;
        }

        $key = self::key($name);

        return Parallel::query()
            ->where('card_set_id', $cardSetId)
            ->orderBy('id')
            ->get()
            ->first(fn (Parallel $parallel): bool => self::key($parallel->name) === $key);
    }

    /**
     * Existing values one or two letters away from what was typed, for "Did you mean…?".
     * Only for typed text of five or more letters, where a near miss is likely a typo.
     *
     * @return list<string>
     */
    public function nearMisses(string $field, string $typed, ?string $sport = null): array
    {
        $key = self::key($typed);

        if (mb_strlen($key) < 5) {
            return [];
        }

        $allowed = mb_strlen($key) >= 9 ? 2 : 1;

        return array_values(collect($this->known($field))
            ->map(fn (array $spellings, string $knownKey): array => ['distance' => levenshtein($key, $knownKey), 'spellings' => $spellings])
            ->filter(fn (array $entry): bool => $entry['distance'] > 0 && $entry['distance'] <= $allowed)
            ->sortBy('distance')
            ->map(function (array $entry) use ($sport): string {
                $match = collect($entry['spellings'])->first(fn (array $known): bool => $sport !== null && $known['sport'] === $sport)
                    ?? $entry['spellings'][0];

                return $match['name'];
            })
            ->take(3)
            ->all());
    }

    /**
     * @return array<string, list<array{name: string, sport: string|null}>>
     */
    private function known(string $field): array
    {
        if (isset($this->known[$field])) {
            return $this->known[$field];
        }

        [$table, $column, $sportColumn] = self::FIELDS[$field];

        $rows = DB::table($table)
            ->select($column)
            ->when($sportColumn, fn ($query, string $sportColumn) => $query->addSelect($sportColumn))
            ->selectRaw('count(*) as uses')
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($sportColumn === null ? [$column] : [$column, $sportColumn])
            ->orderByDesc('uses')
            ->orderBy($column)
            ->get();

        /** @var Collection<string, list<array{name: string, sport: string|null}>> $grouped */
        $grouped = $rows
            ->map(fn (object $row): array => [
                'name' => (string) $row->{$column},
                'sport' => $sportColumn === null ? null : (string) $row->{$sportColumn},
            ])
            ->groupBy(fn (array $known): string => self::key($known['name']))
            ->map(fn (Collection $spellings): array => $spellings->values()->all());

        return $this->known[$field] = $grouped->all();
    }
}
