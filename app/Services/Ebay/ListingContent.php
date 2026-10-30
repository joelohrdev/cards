<?php

namespace App\Services\Ebay;

use App\Models\CollectionItem;
use App\Models\ItemImage;
use Illuminate\Support\Str;

/**
 * Builds eBay listing content (title, description, item specifics, photos) from a collection item.
 */
final class ListingContent
{
    /**
     * eBay's maximum listing title length.
     */
    public const int TITLE_LIMIT = 80;

    /**
     * Sports Trading Card Singles.
     */
    public const string CATEGORY_ID = '261328';

    /**
     * Relations needed to build listing content without lazy loading.
     *
     * @var list<string>
     */
    public const array RELATIONS = ['card.cardSet.manufacturer', 'card.team', 'card.players', 'parallel', 'images'];

    public function __construct(private CollectionItem $item) {}

    public static function for(CollectionItem $item): self
    {
        $item->loadMissing(self::RELATIONS);

        return new self($item);
    }

    /**
     * A title of at most 80 characters. When the full title is too long, the least important
     * parts (card number, set name, memorabilia) are dropped before anything is cut off.
     */
    public function title(): string
    {
        $card = $this->item->card;
        $set = $card->cardSet;
        $printRun = $this->printRun();

        /** @var list<array{0: string|null, 1: int}> $parts [text, priority] in display order; higher priority is kept longer */
        $parts = [
            [$set->season ?? (string) $set->year, 90],
            [$set->manufacturer?->name, 55],
            [$set->name, 60],
            [$card->name, 100],
            [$card->number !== '' ? '#'.$card->number : null, 50],
            [$this->item->parallel?->name, 70],
            [$printRun ? ($this->item->serial_number ? $this->item->serial_number.'/'.$printRun : '/'.$printRun) : null, 80],
            [$card->is_rookie ? 'RC' : null, 85],
            [$card->is_autograph ? 'Auto' : null, 85],
            [$card->is_memorabilia ? 'Patch' : null, 45],
            [$this->item->is_graded ? $this->item->conditionLabel() : null, 95],
        ];

        $parts = array_values(array_filter($parts, fn (array $part): bool => filled($part[0])));

        while ($this->joinTitle($parts) !== '' && mb_strlen($this->joinTitle($parts)) > self::TITLE_LIMIT && count($parts) > 1) {
            $lowest = array_keys(array_column($parts, 1), min(array_column($parts, 1)))[0];
            array_splice($parts, $lowest, 1);
        }

        return self::fitTitle($this->joinTitle($parts));
    }

    /**
     * Cut a title down to eBay's limit at a word boundary.
     */
    public static function fitTitle(string $title): string
    {
        $title = trim((string) preg_replace('/\s+/', ' ', $title));

        if (mb_strlen($title) <= self::TITLE_LIMIT) {
            return $title;
        }

        $cut = mb_substr($title, 0, self::TITLE_LIMIT + 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space !== false && $space > 40 ? mb_substr($cut, 0, $space) : mb_substr($title, 0, self::TITLE_LIMIT));
    }

    /**
     * A plain-text description the seller can edit before listing.
     */
    public function description(): string
    {
        $card = $this->item->card;
        $lines = [$this->item->title(), ''];

        foreach ($this->aspects() as $name => $values) {
            if (in_array($name, ['Type', 'Original/Licensed Reprint', 'Graded'], true)) {
                continue;
            }

            $lines[] = $name.': '.implode(', ', $values);
        }

        if ($card->subset) {
            $lines[] = 'Subset: '.$card->subset;
        }

        $lines[] = '';
        $lines[] = 'The card pictured is the exact card you will receive. Ships securely in a penny sleeve and top loader (or slab sleeve for graded cards).';

        return implode("\n", $lines);
    }

    /**
     * Item specifics for category 261328.
     *
     * @return array<string, list<string>>
     */
    public function aspects(): array
    {
        $item = $this->item;
        $card = $item->card;
        $set = $card->cardSet;
        $printRun = $this->printRun();

        $features = array_values(array_filter([
            $card->is_rookie ? 'Rookie' : null,
            $printRun ? 'Serial Numbered' : null,
            $card->is_short_print ? 'Short Print' : null,
            $item->parallel ? 'Parallel/Variety' : null,
            $card->is_memorabilia ? 'Patch' : null,
        ]));

        $aspects = [
            'Type' => ['Sports Trading Card'],
            'Sport' => [$set->sport->label()],
            'Player/Athlete' => $card->players->pluck('name')->all() ?: [$card->name],
            'Manufacturer' => [$set->manufacturer->name ?? null],
            'Set' => [trim(($set->manufacturer->name ?? '').' '.$set->name)],
            'Season' => [$set->season ?? (string) $set->year],
            'Year Manufactured' => [(string) $set->year],
            'Team' => [$card->team?->name],
            'Card Number' => [$card->number],
            'Parallel/Variety' => [$item->parallel?->name],
            'Features' => $features,
            'Autographed' => [$card->is_autograph ? 'Yes' : 'No'],
            'Original/Licensed Reprint' => ['Original'],
            'Graded' => [$item->is_graded ? 'Yes' : 'No'],
            'Professional Grader' => [$item->is_graded ? $this->graderName() : null],
            'Grade' => [$item->is_graded ? TradingCardCondition::normalizeGrade($item->grade) : null],
            'Certification Number' => [$item->is_graded ? $item->cert_number : null],
            'Print Run' => [$printRun ? (string) $printRun : null],
            'Card Condition' => [! $item->is_graded ? $this->cardConditionName() : null],
        ];

        return collect($aspects)
            ->map(fn (array $values): array => array_values(array_map(
                fn (string $value): string => mb_substr($value, 0, 65),
                array_filter($values, fn (?string $value): bool => filled($value)),
            )))
            ->filter()
            ->all();
    }

    /**
     * Public photo URLs, front first.
     *
     * @return list<string>
     */
    public function imageUrls(): array
    {
        return array_values($this->item->images
            ->map(fn (ItemImage $image): string => $image->url)
            ->all());
    }

    /**
     * Photo URLs eBay can download (eBay requires public https URLs). At most 24 are allowed.
     *
     * @return list<string>
     */
    public function publishableImageUrls(): array
    {
        return array_slice(array_values(array_filter(
            $this->imageUrls(),
            fn (string $url): bool => Str::startsWith($url, 'https://'),
        )), 0, 24);
    }

    /**
     * Reasons the item may not list well, shown to the seller before publishing.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        $warnings = [];
        $urls = $this->imageUrls();

        if ($urls === []) {
            $warnings[] = 'This card has no photos. eBay requires at least one, so add a photo of the front (and back) before publishing.';
        } elseif ($this->publishableImageUrls() === []) {
            $warnings[] = 'eBay can only use photos served from a public https:// address. Your photo links start with “'.Str::before($urls[0], '://').'://”. Set APP_URL to your public https domain (and run php artisan storage:link) before publishing.';
        }

        if (! $this->item->is_graded && $this->item->raw_condition === null) {
            $warnings[] = 'This card has no condition set. Edit the card and choose a condition — eBay requires one for ungraded cards.';
        }

        if ($this->item->is_graded && TradingCardCondition::normalizeGrade($this->item->grade) === null) {
            $warnings[] = 'eBay only accepts grades from 1 to 10 in half steps, or Authentic. Edit the card’s grade before publishing.';
        }

        return $warnings;
    }

    /**
     * The inventory item payload for createOrReplaceInventoryItem.
     *
     * @return array<string, mixed>
     *
     * @throws EbayException when the item can't be listed as-is.
     */
    public function inventoryItem(string $title, string $description): array
    {
        $images = $this->publishableImageUrls();

        if ($images === []) {
            throw new EbayException($this->imageUrls() === []
                ? 'Add at least one photo of this card before publishing. eBay requires a photo for every listing.'
                : 'eBay can’t download your photos because they aren’t on a public https:// address. Set APP_URL to your public https domain and try again.');
        }

        return [
            'availability' => ['shipToLocationAvailability' => ['quantity' => 1]],
            ...TradingCardCondition::for($this->item),
            'product' => [
                'title' => $title,
                'description' => self::descriptionHtml($description),
                'aspects' => $this->aspects(),
                'imageUrls' => $images,
            ],
        ];
    }

    /**
     * Escape the seller's plain-text description and keep its line breaks.
     */
    public static function descriptionHtml(string $description): string
    {
        return nl2br(e(trim($description)), false);
    }

    private function printRun(): ?int
    {
        return $this->item->parallel->print_run ?? $this->item->card->print_run;
    }

    private function graderName(): ?string
    {
        return $this->item->grader?->value;
    }

    private function cardConditionName(): ?string
    {
        if ($this->item->raw_condition === null) {
            return null;
        }

        return match (TradingCardCondition::cardConditionValue($this->item->raw_condition)) {
            TradingCardCondition::CARD_NEAR_MINT_OR_BETTER => 'Near Mint or Better',
            TradingCardCondition::CARD_EXCELLENT => 'Excellent',
            TradingCardCondition::CARD_VERY_GOOD => 'Very Good',
            default => 'Poor',
        };
    }

    /**
     * @param  list<array{0: string|null, 1: int}>  $parts
     */
    private function joinTitle(array $parts): string
    {
        return implode(' ', array_column($parts, 0));
    }
}
