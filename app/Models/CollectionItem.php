<?php

namespace App\Models;

use App\Enums\Grader;
use App\Enums\ItemStatus;
use App\Enums\RawCondition;
use Carbon\CarbonImmutable;
use Database\Factories\CollectionItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A physical card owned by a user.
 *
 * Money columns are stored in cents.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $card_id
 * @property int|null $parallel_id
 * @property int|null $serial_number
 * @property bool $is_graded
 * @property Grader|null $grader
 * @property string|null $grade
 * @property string|null $cert_number
 * @property RawCondition|null $raw_condition
 * @property ItemStatus $status
 * @property int|null $purchase_price
 * @property CarbonImmutable|null $purchase_date
 * @property string|null $purchase_source
 * @property int|null $estimated_value
 * @property CarbonImmutable|null $value_updated_at
 * @property int|null $storage_location_id
 * @property string|null $storage_position
 * @property string|null $notes
 * @property CarbonImmutable $created_at
 * @property-read Card $card
 * @property-read Parallel|null $parallel
 * @property-read StorageLocation|null $storageLocation
 * @property-read Sale|null $sale
 */
#[Fillable([
    'card_id', 'parallel_id', 'serial_number', 'is_graded', 'grader', 'grade', 'cert_number',
    'raw_condition', 'status', 'purchase_price', 'purchase_date', 'purchase_source',
    'estimated_value', 'value_updated_at', 'storage_location_id', 'storage_position', 'notes',
])]
class CollectionItem extends Model
{
    /** @use HasFactory<CollectionItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_graded' => 'boolean',
            'grader' => Grader::class,
            'raw_condition' => RawCondition::class,
            'status' => ItemStatus::class,
            'purchase_price' => 'integer',
            'purchase_date' => 'date',
            'estimated_value' => 'integer',
            'value_updated_at' => 'datetime',
            'serial_number' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Card, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * @return BelongsTo<Parallel, $this>
     */
    public function parallel(): BelongsTo
    {
        return $this->belongsTo(Parallel::class);
    }

    /**
     * @return BelongsTo<StorageLocation, $this>
     */
    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @return HasMany<ItemImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ItemImage::class)->orderBy('position');
    }

    /**
     * @return HasOne<ItemImage, $this>
     */
    public function frontImage(): HasOne
    {
        return $this->hasOne(ItemImage::class)->ofMany(['position' => 'min', 'id' => 'min']);
    }

    /**
     * @return HasOne<Sale, $this>
     */
    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }

    /**
     * @return HasMany<EbayListing, $this>
     */
    public function ebayListings(): HasMany
    {
        return $this->hasMany(EbayListing::class);
    }

    /**
     * @return BelongsToMany<GradingSubmission, $this>
     */
    public function gradingSubmissions(): BelongsToMany
    {
        return $this->belongsToMany(GradingSubmission::class, 'grading_submission_items')
            ->withPivot(['declared_value', 'fee', 'resulting_grade', 'resulting_cert_number'])
            ->withTimestamps();
    }

    /**
     * Limit the query to cards still physically in the collection.
     *
     * @param  Builder<CollectionItem>  $query
     */
    public function scopeInHand(Builder $query): void
    {
        $query->whereIn('status', ItemStatus::inHand());
    }

    /**
     * Human-readable condition, e.g. "PSA 10" or "Near Mint".
     */
    public function conditionLabel(): ?string
    {
        if ($this->is_graded) {
            return trim(($this->grader->value ?? '').' '.($this->grade ?? ''));
        }

        return $this->raw_condition?->label();
    }

    /**
     * A one-line title, e.g. "2018 Panini Prizm #1 Shohei Ohtani Silver /99 PSA 10".
     */
    public function title(): string
    {
        $card = $this->card;
        $parallel = $this->parallel;

        $parts = [
            $card->cardSet->full_name,
            '#'.$card->number,
            $card->name,
            $card->is_rookie ? 'RC' : null,
            $parallel?->name,
            $this->serial_number && ($parallel->print_run ?? $card->print_run)
                ? $this->serial_number.'/'.($parallel->print_run ?? $card->print_run)
                : (($parallel->print_run ?? $card->print_run) ? '/'.($parallel->print_run ?? $card->print_run) : null),
            $card->is_autograph ? 'Auto' : null,
            $card->is_memorabilia ? 'Patch/Relic' : null,
            $this->is_graded ? $this->conditionLabel() : null,
        ];

        return implode(' ', array_filter($parts));
    }
}
