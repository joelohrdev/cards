<?php

namespace App\Services\CardIdentification;

use App\Models\Card;
use App\Support\CatalogCardSearch;

/**
 * Finds catalog cards that look like a photo suggestion, from most to least specific search.
 *
 * The looser searches only need a player name, so a card is kept only when its year and number
 * agree with what the photo shows. Otherwise any card naming that player, such as a league
 * leaders card, would be picked for a scan of a different card.
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

            $cards = $this->catalogCardSearch->search(array_values(array_unique($words)), limit: 10)
                ->filter(fn (Card $card): bool => $this->agreesWith($card, $suggestion));

            if ($cards->isNotEmpty()) {
                return array_values($cards->map(fn (Card $card): array => CatalogCardSearch::present($card))->all());
            }
        }

        return [];
    }

    /**
     * Whether the card's year and number match the photo's, where the photo shows them.
     *
     * @param  array{year: int|null, set_name: string|null, number: string|null, name: string|null}  $suggestion
     */
    private function agreesWith(Card $card, array $suggestion): bool
    {
        if ($suggestion['year'] !== null && $card->cardSet->year !== $suggestion['year']) {
            return false;
        }

        return $suggestion['number'] === null
            || strcasecmp(ltrim($card->number, '#'), ltrim($suggestion['number'], '#')) === 0;
    }
}
