<?php

namespace App\Services\CardIdentification;

use App\Models\Player;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Swaps the player names a photo was read as for the spelling of players already in the app,
 * so a scan doesn't add "Ken Griffey Jr" next to an existing "Ken Griffey Jr.".
 *
 * Names count as the same when they differ only in capitalization, punctuation, accents or
 * spacing. Anything else is left as read, since a looser match could pick the wrong player.
 */
class PlayerNameMatcher
{
    /**
     * Match each name in a "/"- or "&"-separated list, keeping the ones with no match as read.
     */
    public function match(?string $names, ?string $sport = null): ?string
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

        $wanted = $parts->map(fn (string $name): string => self::key($name))->unique()->all();
        $existing = $this->playersWithKeys($wanted);

        return $parts
            ->map(function (string $name) use ($existing, $sport): string {
                $candidates = $existing->get(self::key($name), collect());

                $match = $candidates->first(fn (Player $player): bool => $sport !== null && $player->sport->value === $sport)
                    ?? $candidates->first();

                return $match->name ?? $name;
            })
            ->implode(' / ');
    }

    /**
     * Players whose name key is one of the wanted keys, grouped by key.
     *
     * @param  array<int, string>  $keys
     * @return Collection<string, Collection<int, Player>>
     */
    private function playersWithKeys(array $keys): Collection
    {
        return Player::query()
            ->orderBy('id')
            ->get(['id', 'name', 'sport'])
            ->toBase()
            ->groupBy(fn (Player $player): string => self::key($player->name))
            ->only($keys);
    }

    /**
     * "Ronald Acuña Jr." and "ronald acuna jr" both become "ronald acuna jr".
     */
    public static function key(string $name): string
    {
        $ascii = Str::lower(Str::ascii($name));

        return Str::squish((string) preg_replace('/[^a-z0-9 ]+/', ' ', str_replace(['.', "'", '’'], '', $ascii)));
    }
}
