<?php

namespace App\Support\Export;

use App\Models\CollectionItem;
use App\Models\Player;
use App\Models\StorageLocation;
use App\Models\Tag;
use App\Support\Money;

/**
 * A full, re-importable CSV of the collection.
 *
 * The headings are chosen so the importer's auto-mapping recognises every column it can use
 * (only ID, Graded and Created are informational).
 */
final class CollectionCsv
{
    /**
     * @var list<string>
     */
    public const array HEADERS = [
        'ID', 'Year', 'Sport', 'Brand', 'Set', 'Card #', 'Name', 'Players', 'Team', 'Subset', 'Parallel',
        'Serial', 'Print run', 'RC', 'Auto', 'Relic', 'SP', 'Graded', 'Grader', 'Grade', 'Cert', 'Condition',
        'Status', 'Paid', 'Value', 'Purchase date', 'Source', 'Location', 'Position', 'Tags', 'Notes', 'Created',
    ];

    /**
     * Write the CSV to an open stream, a few hundred cards at a time.
     *
     * @param  resource  $stream
     */
    public function write($stream): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, self::HEADERS, escape: '');

        $locationPaths = $this->locationPaths();

        CollectionItem::query()
            ->with(['card.cardSet.manufacturer', 'card.team', 'card.players', 'parallel', 'tags'])
            ->chunkById(500, function ($items) use ($stream, $locationPaths): void {
                foreach ($items as $item) {
                    fputcsv($stream, $this->row($item, $locationPaths), escape: '');
                }
            });
    }

    public function filename(): string
    {
        return 'card-collection-'.now()->format('Y-m-d').'.csv';
    }

    /**
     * @param  array<int, string>  $locationPaths
     * @return list<string|int|null>
     */
    private function row(CollectionItem $item, array $locationPaths): array
    {
        $card = $item->card;
        $set = $card->cardSet;
        $printRun = $item->parallel->print_run ?? $card->print_run;
        $yesNo = fn (bool $value): string => $value ? 'Yes' : '';

        return array_map(self::neutralizeFormula(...), [
            $item->id,
            $set->season ?? $set->year,
            $set->sport->label(),
            $set->manufacturer?->name,
            $set->name,
            $card->number,
            $card->name,
            $card->players->map(fn (Player $player): string => $player->name)->implode(' / '),
            $card->team?->name,
            $card->subset,
            $item->parallel?->name,
            $item->serial_number,
            $printRun,
            $yesNo($card->is_rookie),
            $yesNo($card->is_autograph),
            $yesNo($card->is_memorabilia),
            $yesNo($card->is_short_print),
            $item->is_graded ? 'Yes' : 'No',
            $item->grader?->value,
            $item->grade,
            $item->cert_number,
            $item->is_graded ? null : $item->raw_condition?->label(),
            $item->status->label(),
            Money::toDollars($item->purchase_price),
            Money::toDollars($item->estimated_value),
            $item->purchase_date?->toDateString(),
            $item->purchase_source,
            $item->storage_location_id === null ? null : ($locationPaths[$item->storage_location_id] ?? null),
            $item->storage_position,
            $item->tags->map(fn (Tag $tag): string => $tag->name)->implode(', '),
            $item->notes,
            $item->created_at->toDateString(),
        ]);
    }

    /**
     * Breadcrumb paths for every location, built from one query rather than one per card.
     *
     * @return array<int, string>
     */
    private function locationPaths(): array
    {
        $locations = StorageLocation::query()->get(['id', 'parent_id', 'name'])->keyBy('id');
        $paths = [];

        foreach ($locations as $location) {
            $names = [$location->name];
            $parent = $location->parent_id === null ? null : $locations->get($location->parent_id);
            $depth = 0;

            while ($parent instanceof StorageLocation && $depth < 10) {
                array_unshift($names, $parent->name);
                $parent = $parent->parent_id === null ? null : $locations->get($parent->parent_id);
                $depth++;
            }

            $paths[$location->id] = implode(' / ', $names);
        }

        return $paths;
    }

    /**
     * Stop spreadsheet apps from running a cell as a formula (CSV injection).
     */
    private static function neutralizeFormula(string|int|null $value): string|int|null
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
