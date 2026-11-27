<?php

namespace App\Services\CardIdentification;

use App\Models\Manufacturer;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Swaps the player, team and brand names a photo was read as for the spelling already in the
 * app, so a scan doesn't add "Ken Griffey Jr" next to an existing "Ken Griffey Jr." or
 * "upper deck" next to "Upper Deck".
 *
 * Names count as the same when they differ only in capitalization, punctuation, accents or
 * spacing. Anything else is left as read, since a looser match could pick the wrong one.
 */
class ExistingNameMatcher
{
    /**
     * Match each name in a "/"- or "&"-separated list of players.
     */
    public function players(?string $names, ?string $sport = null): ?string
    {
        if (blank($names)) {
            return $names;
        }

        $parts = collect(preg_split('/\s*(?:\/|&)\s*/', $names) ?: [])
            ->map(fn (string $name): string => Str::squish($name))
            ->filter()
            ->values();

        if ($parts->isEmpty()) {
            return $names;
        }

        $existing = self::byKey(Player::query()->get(['id', 'name', 'sport']));

        return $parts
            ->map(fn (string $name): string => self::pick($existing, $name, $sport))
            ->implode(' / ');
    }

    public function team(?string $name, ?string $sport = null): ?string
    {
        return blank($name) ? $name : self::pick(self::byKey(Team::query()->get(['id', 'name', 'sport'])), $name, $sport);
    }

    public function manufacturer(?string $name): ?string
    {
        return blank($name) ? $name : self::pick(self::byKey(Manufacturer::query()->get(['id', 'name'])), $name);
    }

    /**
     * "Ronald Acuña Jr." and "ronald acuna jr" both become "ronaldacunajr"; "UpperDeck" and
     * "Upper Deck" both become "upperdeck".
     */
    public static function key(string $name): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii($name)));
    }

    /**
     * @param  iterable<int, Player|Team|Manufacturer>  $models
     * @return Collection<string, Collection<int, Player|Team|Manufacturer>>
     */
    private static function byKey(iterable $models): Collection
    {
        return collect($models)
            ->sortBy(fn (Model $model): mixed => $model->getKey())
            ->groupBy(fn (Player|Team|Manufacturer $model): string => self::key($model->name));
    }

    /**
     * The existing spelling of a name, preferring one from the card's sport, or the name as read.
     *
     * @param  Collection<string, Collection<int, Player|Team|Manufacturer>>  $existing
     */
    private static function pick(Collection $existing, string $name, ?string $sport = null): string
    {
        $name = Str::squish($name);
        $candidates = $existing->get(self::key($name), collect());

        $match = $candidates->first(fn (Player|Team|Manufacturer $model): bool => $sport !== null
            && ! $model instanceof Manufacturer
            && $model->sport->value === $sport)
            ?? $candidates->first();

        return $match->name ?? $name;
    }
}
