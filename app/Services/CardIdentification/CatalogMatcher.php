<?php

namespace App\Services\CardIdentification;

use App\Models\Card;
use App\Support\CatalogCardSearch;

/**
 * Finds catalog cards that look like a photo suggestion, from most to least specific search.
 */
class CatalogMatcher
{
    public function __construct(private CatalogCardSearch $catalogCardSearch) {}

    /**
     * @param  array{year: int|null, set_name: string|null, number: string|null, name: string|null}  $suggestion
     * @return list<array<string, mixed>>
     */
    public function matches(array $suggestion): array
    {
        $year = $suggestion['year'] === null ? [] : [(string) $suggestion['year']];
        $number = $suggestion['number'] === null ? [] : ['#'.$suggestion['number']];
        $nameWords = CatalogCardSearch::words($suggestion['name']);
        $lastName = $nameWords === [] ? [] : [end($nameWords)];
        $setWords = CatalogCardSearch::words($suggestion['set_name']);

        $attempts = [
            [...$year, ...$setWords, ...$number, ...$lastName],
            [...$year, ...$number, ...$lastName],
            [...$year, ...$nameWords],
            $nameWords,
        ];

        foreach ($attempts as $words) {
            if ($words === [] || ($nameWords === [] && $number === [])) {
                continue;
            }

            $cards = $this->catalogCardSearch->search(array_values(array_unique($words)), limit: 10);

            if ($cards->isNotEmpty()) {
                return array_values($cards->map(fn (Card $card): array => CatalogCardSearch::present($card))->all());
            }
        }

        return [];
    }
}
