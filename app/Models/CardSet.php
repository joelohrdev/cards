<?php

namespace App\Models;

use App\Enums\Sport;
use Database\Factories\CardSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $manufacturer_id
 * @property Sport $sport
 * @property int $year
 * @property string|null $season
 * @property string $name
 * @property int|null $card_count
 * @property string|null $notes
 * @property-read string $full_name
 * @property-read int $cards_count
 * @property-read int $owned_count
 * @property-read Manufacturer|null $manufacturer
 */
#[Fillable(['manufacturer_id', 'sport', 'year', 'season', 'name', 'card_count', 'notes'])]
class CardSet extends Model
{
    /** @use HasFactory<CardSetFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sport' => Sport::class,
            'year' => 'integer',
            'card_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Manufacturer, $this>
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    /**
     * @return HasMany<Card, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    /**
     * @return HasMany<Parallel, $this>
     */
    public function parallels(): HasMany
    {
        return $this->hasMany(Parallel::class);
    }

    /**
     * The display name, e.g. "2018 Panini Prizm".
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim(implode(' ', array_filter([
            $this->season ?? (string) $this->year,
            $this->manufacturer?->name,
            $this->name,
        ]))));
    }
}
