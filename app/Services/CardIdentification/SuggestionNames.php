<?php

namespace App\Services\CardIdentification;

use App\Models\CardSet;
use App\Support\ExistingNames;

/**
 * Gives a photo suggestion the spellings already in the app (players, team, brand, set) and
 * finds the existing set it belongs to.
 *
 * @phpstan-import-type CardReading from CardIdentifier
 */
class SuggestionNames
{
    public function __construct(private ExistingNames $existingNames) {}

    /**
     * @param  CardReading  $suggestion
     * @return array{suggestion: CardReading, set: CardSet|null}
     */
    public function apply(array $suggestion): array
    {
        $suggestion['name'] = $this->existingNames->players($suggestion['name'], $suggestion['sport']);
        $suggestion['team'] = $this->existingNames->spelling('team', $suggestion['team'], $suggestion['sport']);
        $suggestion['manufacturer'] = $this->existingNames->spelling('manufacturer', $suggestion['manufacturer']);
        $set = $this->existingNames->set($suggestion['set_name'], $suggestion['year'], $suggestion['manufacturer'], $suggestion['sport']);
        $suggestion['set_name'] = $set->name ?? $this->existingNames->setName($suggestion['set_name'], $suggestion['manufacturer']);

        if ($set !== null && $set->manufacturer !== null) {
            $suggestion['manufacturer'] = $set->manufacturer->name;
        }

        return ['suggestion' => $suggestion, 'set' => $set];
    }
}
