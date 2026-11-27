<?php

namespace App\Actions\Catalog;

use App\Enums\Sport;
use App\Models\CardSet;
use App\Models\Manufacturer;
use App\Support\ExistingNames;

/**
 * Find or create a set from loose descriptors such as those found in a spreadsheet row. A set
 * whose year, sport, brand and name match an existing one apart from spelling is that set.
 */
class ResolveCardSet
{
    public function __construct(private ExistingNames $existingNames) {}

    /**
     * @param  array{season?: string|null, card_count?: int|null, notes?: string|null}  $details  Used only when the set is new.
     */
    public function handle(Sport $sport, int $year, ?string $manufacturerName, string $setName, ?string $season = null, array $details = []): CardSet
    {
        $manufacturer = $this->manufacturer($manufacturerName);

        $existing = $this->existingNames->set($setName, $year, $manufacturer?->name, $sport->value);

        if ($existing !== null) {
            return $existing;
        }

        return CardSet::query()->firstOrCreate([
            'sport' => $sport,
            'year' => $year,
            'manufacturer_id' => $manufacturer?->id,
            'name' => $this->existingNames->setName($setName, $manufacturer?->name),
        ], [
            'season' => $season,
            ...$details,
        ]);
    }

    /**
     * The brand in its existing spelling, created if it's new.
     */
    public function manufacturer(?string $name): ?Manufacturer
    {
        $name = $this->existingNames->spelling('manufacturer', $name);

        return $name === null ? null : Manufacturer::query()->firstOrCreate(['name' => $name]);
    }
}
