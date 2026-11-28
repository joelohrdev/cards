<?php

namespace App\Actions\Catalog;

use App\Models\Card;
use App\Models\Player;
use App\Models\Team;
use App\Support\ExistingNames;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Correct a checklist card's details. Every copy of the card shows the change.
 */
class UpdateChecklistCard
{
    public function __construct(private ExistingNames $existingNames) {}

    /**
     * Validation rules for the details, keyed under $prefix (e.g. "card_details.").
     *
     * @return array<string, array<mixed>>
     */
    public static function rules(Card $card, string $prefix = ''): array
    {
        return [
            "{$prefix}number" => ['required', 'string', 'max:30', Rule::unique('cards', 'number')->where('card_set_id', $card->card_set_id)->ignore($card->id)],
            "{$prefix}name" => ['required', 'string', 'max:200'],
            "{$prefix}players" => ['nullable', 'array', 'max:10'],
            "{$prefix}players.*" => ['string', 'max:100'],
            "{$prefix}team" => ['nullable', 'string', 'max:100'],
            "{$prefix}subset" => ['nullable', 'string', 'max:100'],
            "{$prefix}is_rookie" => ['boolean'],
            "{$prefix}is_autograph" => ['boolean'],
            "{$prefix}is_memorabilia" => ['boolean'],
            "{$prefix}is_short_print" => ['boolean'],
            "{$prefix}print_run" => ['nullable', 'integer', 'min:1'],
            "{$prefix}notes" => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $details  Input validated against rules().
     */
    public function handle(Card $card, array $details): Card
    {
        $sport = $card->cardSet->sport;
        $team = $this->existingNames->spelling('team', self::text($details, 'team'), $sport->value);

        $card->update([
            ...Arr::except($details, ['players', 'team']),
            'number' => ltrim(trim((string) self::text($details, 'number')), '#'),
            'subset' => $this->existingNames->spelling('subset', self::text($details, 'subset')),
            'team_id' => $team === null ? null : Team::query()->firstOrCreate(['name' => $team, 'sport' => $sport])->id,
        ]);

        if (array_key_exists('players', $details)) {
            $players = array_filter((array) $details['players'], is_string(...));

            $card->players()->sync(collect($this->existingNames->playerList(implode(' / ', $players), $sport->value))
                ->map(fn (string $name): int => Player::query()->firstOrCreate(['name' => $name, 'sport' => $sport])->id)
                ->all());
        }

        return $card;
    }

    /**
     * A text field from the details, or null when it's missing or empty.
     *
     * @param  array<array-key, mixed>  $details
     */
    private static function text(array $details, string $field): ?string
    {
        $value = $details[$field] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
