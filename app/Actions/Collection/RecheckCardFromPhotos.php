<?php

namespace App\Actions\Collection;

use App\Actions\Catalog\ResolveCard;
use App\Actions\Catalog\ResolveCardSet;
use App\Enums\Sport;
use App\Models\Card;
use App\Models\CollectionItem;
use App\Models\ItemImage;
use App\Models\Parallel;
use App\Services\CardIdentification\CardIdentificationFailed;
use App\Services\CardIdentification\CardIdentifier;
use App\Services\CardIdentification\CatalogMatcher;
use App\Services\CardIdentification\SuggestionNames;
use App\Support\ExistingNames;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Reads a copy's saved photos again and, when they show a different year or card number than
 * the checklist card the copy is linked to, moves the copy to the card in the photos. That card
 * is found in the catalog the way a scan finds it, or created when nobody has added it yet.
 *
 * Only the copy moves: the checklist card it was on and that card's other copies stay as they are.
 *
 * @phpstan-import-type CardReading from CardIdentifier
 */
class RecheckCardFromPhotos
{
    public function __construct(
        private CardIdentifier $identifier,
        private SuggestionNames $suggestionNames,
        private CatalogMatcher $matcher,
        private ResolveCardSet $resolveCardSet,
        private ResolveCard $resolveCard,
        private ExistingNames $existingNames,
    ) {}

    /**
     * @param  bool  $apply  False only reports what would change.
     * @return array{outcome: 'no_photo'|'unreadable'|'unsure'|'correct'|'would_move'|'moved', reading: string|null, target: string|null}
     */
    public function handle(CollectionItem $item, bool $apply): array
    {
        $images = $item->images()->limit(2)->get();

        if ($images->isEmpty()) {
            return ['outcome' => 'no_photo', 'reading' => null, 'target' => null];
        }

        try {
            $reading = $this->read($images->values()->all());
        } catch (CardIdentificationFailed $exception) {
            report($exception);

            return ['outcome' => 'unreadable', 'reading' => null, 'target' => null];
        }

        ['suggestion' => $suggestion] = $this->suggestionNames->apply($reading);
        $description = self::describe($suggestion);

        if ($suggestion['year'] === null || $suggestion['number'] === null || $suggestion['confidence'] === 'low') {
            return ['outcome' => 'unsure', 'reading' => $description, 'target' => null];
        }

        $item->loadMissing(['card.cardSet', 'parallel']);

        if (CatalogMatcher::agrees($item->card, $suggestion)) {
            return ['outcome' => 'correct', 'reading' => $description, 'target' => null];
        }

        $matchId = $this->matcher->matches($suggestion)[0]['id'] ?? null;
        $match = $matchId === null ? null : Card::query()->with('cardSet.manufacturer')->whereKey($matchId)->first();

        if (! $apply) {
            return [
                'outcome' => 'would_move',
                'reading' => $description,
                'target' => $match === null ? "new card: {$description}" : self::cardLabel($match),
            ];
        }

        $target = DB::transaction(function () use ($item, $match, $suggestion): Card {
            $target = $match ?? $this->createCard($item, $suggestion);
            $parallel = $this->parallelOn($target, $item->parallel);

            $item->card()->associate($target);
            $item->parallel()->associate($parallel);
            $item->save();

            return $target;
        });

        return ['outcome' => 'moved', 'reading' => $description, 'target' => self::cardLabel($target->load('cardSet.manufacturer'))];
    }

    /**
     * Send the first photo (the front) and the second (the back) to the model.
     *
     * @param  array<int, ItemImage>  $images
     * @return CardReading
     *
     * @throws CardIdentificationFailed
     */
    private function read(array $images): array
    {
        $files = [];

        try {
            foreach ($images as $image) {
                $contents = ItemImage::disk()->get($image->path);

                if ($contents === null) {
                    throw new CardIdentificationFailed("Photo {$image->path} is missing from the disk.");
                }

                $path = tempnam(sys_get_temp_dir(), 'recheck');
                file_put_contents($path, $contents);
                $files[] = new UploadedFile($path, basename($image->path), null, null, true);
            }

            return $this->identifier->identify($files[0], $files[1] ?? null);
        } finally {
            foreach ($files as $file) {
                @unlink($file->getPathname());
            }
        }
    }

    /**
     * The card in the photos, added to its set's checklist (and the set created if needed).
     *
     * @param  CardReading  $suggestion
     */
    private function createCard(CollectionItem $item, array $suggestion): Card
    {
        $set = $this->resolveCardSet->handle(
            Sport::tryFrom((string) $suggestion['sport']) ?? $item->card->cardSet->sport,
            (int) $suggestion['year'],
            $suggestion['manufacturer'],
            $suggestion['set_name'],
        );

        return $this->resolveCard->handle($set, [
            'number' => (string) $suggestion['number'],
            'name' => $suggestion['name'],
            'team' => $suggestion['team'],
            'is_rookie' => $suggestion['is_rookie'],
            'is_autograph' => $suggestion['is_autograph'],
            'is_memorabilia' => $suggestion['is_memorabilia'],
        ]);
    }

    /**
     * The copy's parallel in the new card's set: the same-named one there, added if it's missing.
     */
    private function parallelOn(Card $card, ?Parallel $parallel): ?Parallel
    {
        if ($parallel === null || $parallel->card_set_id === $card->card_set_id) {
            return $parallel;
        }

        return $this->existingNames->parallel($card->card_set_id, $parallel->name)
            ?? Parallel::query()->firstOrCreate(
                ['card_set_id' => $card->card_set_id, 'name' => $parallel->name],
                ['print_run' => $parallel->print_run],
            );
    }

    /**
     * @param  CardReading  $suggestion
     */
    private static function describe(array $suggestion): string
    {
        return trim(implode(' ', array_filter([
            $suggestion['year'],
            $suggestion['manufacturer'],
            $suggestion['set_name'],
            $suggestion['number'] === null ? null : '#'.$suggestion['number'],
            $suggestion['name'],
        ], filled(...))));
    }

    public static function cardLabel(Card $card): string
    {
        return "{$card->cardSet->full_name} #{$card->number} {$card->name}";
    }
}
