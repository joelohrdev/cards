<?php

namespace App\Models;

use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single checklist entry within a set (the abstract card, not a physical copy).
 *
 * @property int $id
 * @property int $card_set_id
 * @property string $number
 * @property int $sort_order
 * @property string $name
 * @property string|null $subset
 * @property int|null $team_id
 * @property bool $is_rookie
 * @property bool $is_autograph
 * @property bool $is_memorabilia
 * @property bool $is_short_print
 * @property int|null $print_run
 * @property string|null $notes
 * @property-read CardSet $cardSet
 * @property-read Team|null $team
 */
#[Fillable([
    'card_set_id', 'number', 'sort_order', 'name', 'subset', 'team_id', 'is_rookie',
    'is_autograph', 'is_memorabilia', 'is_short_print', 'print_run', 'notes',
])]
class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Card $card): void {
            $card->sort_order = self::sortOrderFor($card->number);
        });
    }

    /**
     * Derive a natural sort key so "2" sorts before "10" and "RC-3" stays grouped.
     */
    public static function sortOrderFor(string $number): int
    {
        return preg_match('/(\d+)/', $number, $matches) ? min((int) $matches[1], 4_000_000) : 0;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_rookie' => 'boolean',
            'is_autograph' => 'boolean',
            'is_memorabilia' => 'boolean',
            'is_short_print' => 'boolean',
            'print_run' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CardSet, $this>
     */
    public function cardSet(): BelongsTo
    {
        return $this->belongsTo(CardSet::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsToMany<Player, $this>
     */
    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class);
    }

    /**
     * @return HasMany<CollectionItem, $this>
     */
    public function collectionItems(): HasMany
    {
        return $this->hasMany(CollectionItem::class);
    }

    /**
     * @return HasMany<PriceComp, $this>
     */
    public function priceComps(): HasMany
    {
        return $this->hasMany(PriceComp::class);
    }
}
