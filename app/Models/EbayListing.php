<?php

namespace App\Models;

use App\Enums\ListingStatus;
use Carbon\CarbonImmutable;
use Database\Factories\EbayListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int $collection_item_id
 * @property string $sku
 * @property string|null $offer_id
 * @property string|null $listing_id
 * @property string $title
 * @property string $description
 * @property int $price
 * @property ListingStatus $status
 * @property string|null $error
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $ended_at
 * @property-read CollectionItem $collectionItem
 */
#[Fillable(['collection_item_id', 'sku', 'offer_id', 'listing_id', 'title', 'description', 'price', 'status', 'error', 'published_at', 'ended_at'])]
class EbayListing extends Model
{
    /** @use HasFactory<EbayListingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ListingStatus::class,
            'price' => 'integer',
            'published_at' => 'datetime',
            'ended_at' => 'datetime',
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
     * @return BelongsTo<CollectionItem, $this>
     */
    public function collectionItem(): BelongsTo
    {
        return $this->belongsTo(CollectionItem::class);
    }

    public function url(): ?string
    {
        if ($this->listing_id === null) {
            return null;
        }

        return rtrim((string) config('services.ebay.web_url'), '/').'/itm/'.$this->listing_id;
    }
}
