<?php

namespace App\Support;

use App\Http\Resources\CardResource;
use App\Models\Card;
use App\Models\Parallel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Finds checklist cards from loose words, e.g. "1986 fleer jordan" or "prizm #1".
 *
 * Every word must match the card number, card name, a player, the set name, the year, or the
 * manufacturer. Shared by the card picker lookup and photo identification.
 */
class CatalogCardSearch
{
    /**
     * @param  list<string>  $words
     * @param  int|null  $limit  Null returns every match, e.g. a whole set's checklist.
     * @return Collection<int, Card>
     */
    public function search(array $words, ?int $setId = null, ?int $limit = 25): Collection
    {
        return Card::query()
            ->with(['cardSet.manufacturer', 'cardSet.parallels', 'players', 'team'])
            ->when($setId, fn (Builder $cards, int $set) => $cards->where('card_set_id', $set))
            ->where(function (Builder $cards) use ($words): void {
                foreach ($words as $word) {
                    $number = ltrim($word, '#');
                    $cards->where(fn (Builder $card) => $card->where('number', $number)
                        ->orWhereLike('name', "%{$word}%")
                        ->orWhereHas('players', fn (Builder $players) => $players->whereLike('name', "%{$word}%"))
                        ->orWhereHas('cardSet', fn (Builder $set) => $set->whereLike('name', "%{$word}%")
                            ->orWhere('year', $word)
                            ->orWhereHas('manufacturer', fn (Builder $manufacturer) => $manufacturer->whereLike('name', "%{$word}%"))));
                }
            })
            ->orderBy('card_set_id')
            ->orderBy('sort_order')
            ->when($limit, fn (Builder $cards, int $max) => $cards->limit($max))
            ->get();
    }

    /**
     * The card picker's shape for one card: the card resource plus its set's parallels.
     *
     * @return array<string, mixed>
     */
    public static function present(Card $card): array
    {
        return [
            ...CardResource::make($card)->resolve(),
            'parallels' => $card->cardSet->parallels
                ->sortBy('name')
                ->map(fn (Parallel $parallel): array => $parallel->only(['id', 'name', 'print_run']))
                ->values(),
        ];
    }

    /**
     * Split free text into search words.
     *
     * @return list<string>
     */
    public static function words(?string $text): array
    {
        return array_values(array_filter(explode(' ', trim((string) $text))));
    }
}
