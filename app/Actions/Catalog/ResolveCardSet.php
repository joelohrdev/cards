<?php

namespace App\Actions\Catalog;

use App\Enums\Sport;
use App\Models\CardSet;
use App\Models\Manufacturer;

/**
 * Find or create a set from loose descriptors such as those found in a spreadsheet row.
 */
class ResolveCardSet
{
    public function handle(Sport $sport, int $year, ?string $manufacturerName, string $setName, ?string $season = null): CardSet
    {
        $manufacturer = filled($manufacturerName)
            ? Manufacturer::query()->firstOrCreate(['name' => trim((string) $manufacturerName)])
            : null;

        return CardSet::query()->firstOrCreate([
            'sport' => $sport,
            'year' => $year,
            'manufacturer_id' => $manufacturer?->id,
            'name' => trim($setName),
        ], [
            'season' => $season,
        ]);
    }
}
