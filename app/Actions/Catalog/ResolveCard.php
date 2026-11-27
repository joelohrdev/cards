<?php

namespace App\Actions\Catalog;

use App\Models\Card;
use App\Models\CardSet;
use App\Models\Player;
use App\Models\Team;
use App\Support\ExistingNames;
use Illuminate\Support\Str;

/**
 * Find or create a checklist card in a set, filling in any details provided. Players, team and
 * subset take the existing spelling when they match one apart from capitals or punctuation.
 */
class ResolveCard
{
    public function __construct(private ExistingNames $existingNames) {}

    /**
     * @param  array{
     *     number: string,
     *     name?: string|null,
     *     players?: list<string>|null,
     *     team?: string|null,
     *     subset?: string|null,
     *     is_rookie?: bool|null,
     *     is_autograph?: bool|null,
     *     is_memorabilia?: bool|null,
     *     is_short_print?: bool|null,
     *     print_run?: int|null,
     * }  $attributes
     */
    public function handle(CardSet $cardSet, array $attributes): Card
    {
        $number = ltrim(trim($attributes['number']), '#');
        $card = Card::query()->firstOrNew([
            'card_set_id' => $cardSet->id,
            'number' => $number,
        ]);

        $sport = $cardSet->sport->value;
        $playersFromName = ! $card->exists && blank($attributes['players'] ?? null) && filled($attributes['name'] ?? null);
        $players = collect($playersFromName
            ? $this->existingNames->playerList($attributes['name'] ?? null, $sport)
            : $this->existingNames->playerList(implode(' / ', $attributes['players'] ?? []), $sport));

        $name = $attributes['name'] ?? null;
        $card->name = match (true) {
            $playersFromName && $players->isNotEmpty() => $players->implode(' / '),
            filled($name) => Str::squish((string) $name),
            default => $card->name ?? $players->implode(' / '),
        };

        if (blank($card->name)) {
            $card->name = 'Card #'.$number;
        }

        if (filled($attributes['subset'] ?? null)) {
            $card->subset = $this->existingNames->spelling('subset', (string) $attributes['subset']);
        }

        if (filled($attributes['print_run'] ?? null)) {
            $card->print_run = (int) $attributes['print_run'];
        }

        foreach (['is_rookie', 'is_autograph', 'is_memorabilia', 'is_short_print'] as $flag) {
            if (array_key_exists($flag, $attributes) && $attributes[$flag] !== null) {
                $card->{$flag} = (bool) $attributes[$flag];
            }
        }

        if (filled($attributes['team'] ?? null)) {
            $card->team_id = Team::query()->firstOrCreate([
                'name' => $this->existingNames->spelling('team', (string) $attributes['team'], $sport),
                'sport' => $cardSet->sport,
            ])->id;
        }

        $card->save();

        if ($players->isNotEmpty()) {
            $card->players()->sync($players->map(fn (string $playerName): int => Player::query()->firstOrCreate([
                'name' => $playerName,
                'sport' => $cardSet->sport,
            ])->id)->all());
        }

        return $card;
    }
}
