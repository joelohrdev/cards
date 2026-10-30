<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\EbayAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $ebay_user_id
 * @property string|null $username
 * @property string $access_token
 * @property CarbonImmutable $access_token_expires_at
 * @property string $refresh_token
 * @property CarbonImmutable|null $refresh_token_expires_at
 * @property string|null $fulfillment_policy_id
 * @property string|null $payment_policy_id
 * @property string|null $return_policy_id
 * @property string|null $merchant_location_key
 * @property CarbonImmutable|null $orders_synced_at
 * @property-read User $user
 */
#[Fillable([
    'ebay_user_id', 'username', 'access_token', 'access_token_expires_at', 'refresh_token',
    'refresh_token_expires_at', 'fulfillment_policy_id', 'payment_policy_id', 'return_policy_id',
    'merchant_location_key', 'orders_synced_at',
])]
#[Hidden(['access_token', 'refresh_token'])]
class EbayAccount extends Model
{
    /** @use HasFactory<EbayAccountFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
            'orders_synced_at' => 'datetime',
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
     * Whether listing defaults are configured well enough to publish offers.
     */
    public function isReadyToList(): bool
    {
        return filled($this->fulfillment_policy_id)
            && filled($this->payment_policy_id)
            && filled($this->return_policy_id)
            && filled($this->merchant_location_key);
    }
}
