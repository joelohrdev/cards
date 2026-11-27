<?php

namespace App\Actions\Names;

use App\Models\Card;
use App\Models\CardSet;
use App\Models\Manufacturer;
use App\Models\Parallel;
use App\Models\Player;
use App\Models\Tag;
use App\Models\Team;
use App\Models\WantListEntry;
use App\Support\DuplicateNames;
use App\Support\ExistingNames;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Merges near-duplicate names into the one to keep: everything that used a duplicate moves to
 * the kept record, and the duplicates are deleted. Merging sets also merges cards with the same
 * number and parallels with the same name, so nothing is lost.
 */
class MergeDuplicateNames
{
    public function __construct(private DuplicateNames $duplicateNames) {}

    /**
     * @param  list<string>  $merge  Ids (for text fields, the encoded values) to fold into $keep.
     *
     * @throws ValidationException when the records aren't spellings of the same thing.
     */
    public function handle(string $type, string $keep, array $merge): void
    {
        $merge = array_values(array_diff(array_unique($merge), [$keep]));
        $records = collect($this->duplicateNames->records($type))->keyBy('id');
        $signature = $records->get($keep)['signature'] ?? null;

        if ($signature === null || $merge === [] || collect($merge)->contains(fn (string $id): bool => ($records->get($id)['signature'] ?? null) !== $signature)) {
            throw ValidationException::withMessages(['merge' => 'These names have changed since the page loaded. Refresh and try again.']);
        }

        DB::transaction(function () use ($type, $keep, $merge, $records): void {
            if (in_array($type, DuplicateNames::TEXT_TYPES, true)) {
                [$table, $column] = ExistingNames::FIELDS[$type];
                $values = array_map(fn (string $id): string => $records->get($id)['name'], $merge);
                DB::table($table)->whereIn($column, $values)->update([$column => $records->get($keep)['name']]);

                return;
            }

            $ids = array_map(intval(...), $merge);

            match ($type) {
                'player' => $this->players(Player::query()->findOrFail((int) $keep), array_values(Player::query()->findMany($ids)->all())),
                'team' => $this->teams((int) $keep, $ids),
                'manufacturer' => $this->manufacturers(Manufacturer::query()->findOrFail((int) $keep), $ids),
                'set' => collect(CardSet::query()->findMany($ids))->each(fn (CardSet $set) => $this->sets(CardSet::query()->findOrFail((int) $keep), $set)),
                'parallel' => collect(Parallel::query()->findMany($ids))->each(fn (Parallel $parallel) => $this->parallels(Parallel::query()->findOrFail((int) $keep), $parallel)),
                'tag' => $this->tags((int) $keep, $ids),
                default => throw ValidationException::withMessages(['merge' => 'Unknown kind of name.']),
            };
        });
    }

    /**
     * @param  list<Player>  $duplicates
     */
    private function players(Player $keep, array $duplicates): void
    {
        foreach ($duplicates as $duplicate) {
            $alreadyOnCards = DB::table('card_player')->where('player_id', $keep->id)->pluck('card_id');

            DB::table('card_player')->where('player_id', $duplicate->id)->whereIn('card_id', $alreadyOnCards)->delete();
            DB::table('card_player')->where('player_id', $duplicate->id)->update(['player_id' => $keep->id]);
            Card::query()->where('name', $duplicate->name)->update(['name' => $keep->name]);

            $duplicate->delete();
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function teams(int $keep, array $ids): void
    {
        Card::query()->whereIn('team_id', $ids)->update(['team_id' => $keep]);
        Team::query()->whereKey($ids)->delete();
    }

    /**
     * Sets of a duplicate brand move to the kept brand, merging into a set the kept brand already
     * has for the same sport, year and name.
     *
     * @param  list<int>  $ids
     */
    private function manufacturers(Manufacturer $keep, array $ids): void
    {
        foreach (CardSet::query()->whereIn('manufacturer_id', $ids)->get() as $set) {
            $twin = CardSet::query()
                ->where('manufacturer_id', $keep->id)
                ->where('sport', $set->sport)
                ->where('year', $set->year)
                ->get()
                ->first(fn (CardSet $candidate): bool => ExistingNames::key($candidate->name) === ExistingNames::key($set->name));

            if ($twin !== null) {
                $this->sets($twin, $set);
            } else {
                $set->update(['manufacturer_id' => $keep->id]);
            }
        }

        Manufacturer::query()->whereKey($ids)->delete();
    }

    /**
     * Fold a duplicate set into the kept one, card by card and parallel by parallel.
     */
    private function sets(CardSet $keep, CardSet $duplicate): void
    {
        foreach ($duplicate->parallels()->get() as $parallel) {
            $twin = $keep->parallels()->get()->first(fn (Parallel $candidate): bool => ExistingNames::key($candidate->name) === ExistingNames::key($parallel->name));

            if ($twin !== null) {
                $this->parallels($twin, $parallel);
            } else {
                $parallel->update(['card_set_id' => $keep->id]);
            }
        }

        foreach ($duplicate->cards()->get() as $card) {
            $twin = $keep->cards()->where('number', $card->number)->first();

            if ($twin !== null) {
                $this->cards($twin, $card);
            } else {
                $card->update(['card_set_id' => $keep->id]);
            }
        }

        $keep->update([
            'card_count' => max((int) $keep->card_count, (int) $duplicate->card_count) ?: null,
            'notes' => $keep->notes ?? $duplicate->notes,
            'season' => $keep->season ?? $duplicate->season,
        ]);

        $duplicate->delete();
    }

    /**
     * Fold a card into its twin with the same number: copies, comps, want list entries and players
     * move over, and details the twin is missing are filled in.
     */
    private function cards(Card $keep, Card $duplicate): void
    {
        $duplicate->collectionItems()->update(['card_id' => $keep->id]);
        $duplicate->priceComps()->update(['card_id' => $keep->id]);
        $this->moveWantListEntries('card_id', $duplicate->id, $keep->id);

        $keep->players()->syncWithoutDetaching($duplicate->players()->pluck('players.id')->all());

        $keep->update([
            'team_id' => $keep->team_id ?? $duplicate->team_id,
            'subset' => $keep->subset ?? $duplicate->subset,
            'print_run' => $keep->print_run ?? $duplicate->print_run,
            'notes' => $keep->notes ?? $duplicate->notes,
            'is_rookie' => $keep->is_rookie || $duplicate->is_rookie,
            'is_autograph' => $keep->is_autograph || $duplicate->is_autograph,
            'is_memorabilia' => $keep->is_memorabilia || $duplicate->is_memorabilia,
            'is_short_print' => $keep->is_short_print || $duplicate->is_short_print,
        ]);

        $duplicate->delete();
    }

    private function parallels(Parallel $keep, Parallel $duplicate): void
    {
        DB::table('collection_items')->where('parallel_id', $duplicate->id)->update(['parallel_id' => $keep->id]);
        DB::table('price_comps')->where('parallel_id', $duplicate->id)->update(['parallel_id' => $keep->id]);
        $this->moveWantListEntries('parallel_id', $duplicate->id, $keep->id);

        if ($keep->print_run === null && $duplicate->print_run !== null) {
            $keep->update(['print_run' => $duplicate->print_run]);
        }

        $duplicate->delete();
    }

    /**
     * @param  list<int>  $ids
     */
    private function tags(int $keep, array $ids): void
    {
        $alreadyTagged = DB::table('collection_item_tag')->where('tag_id', $keep)->pluck('collection_item_id');

        DB::table('collection_item_tag')->whereIn('tag_id', $ids)->whereIn('collection_item_id', $alreadyTagged)->delete();
        DB::table('collection_item_tag')->whereIn('tag_id', $ids)->update(['tag_id' => $keep]);
        DB::table('share_links')->whereIn('tag_id', $ids)->update(['tag_id' => $keep]);
        DB::table('imports')->whereIn('tag_id', $ids)->update(['tag_id' => $keep]);

        Tag::query()->whereKey($ids)->delete();
    }

    /**
     * Point want list entries at the kept card or parallel, dropping any that would then repeat
     * an entry the same person already has.
     */
    private function moveWantListEntries(string $column, int $from, int $to): void
    {
        foreach (WantListEntry::query()->where($column, $from)->get() as $entry) {
            $moved = [...$entry->only(['user_id', 'card_id', 'parallel_id']), $column => $to];

            if (WantListEntry::query()->where($moved)->exists()) {
                $entry->delete();
            } else {
                $entry->update([$column => $to]);
            }
        }
    }
}
