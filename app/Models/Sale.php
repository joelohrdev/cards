<?php

namespace App\Models;

use App\Enums\SalePlatform;
use Carbon\CarbonImmutable;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int $collection_item_id
 * @property SalePlatform $platform
 * @property CarbonImmutable $sold_on
 * @property int $sale_price
 * @property int $fees
 * @property int $shipping_cost
 * @property string|null $buyer
 * @property string|null $external_order_id
 * @property string|null $notes
 * @property-read CollectionItem $collectionItem
 */
#[Fillable(['collection_item_id', 'platform', 'sold_on', 'sale_price', 'fees', 'shipping_cost', 'buyer', 'external_order_id', 'notes'])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => SalePlatform::class,
            'sold_on' => 'date',
            'sale_price' => 'integer',
            'fees' => 'integer',
            'shipping_cost' => 'integer',
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

    /**
     * Proceeds after fees and shipping, in cents.
     */
    public function netProceeds(): int
    {
        return $this->sale_price - $this->fees - $this->shipping_cost;
    }
}
