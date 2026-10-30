<?php

namespace App\Models;

use App\Enums\ApprovalDecision;
use Carbon\CarbonImmutable;
use Database\Factories\ListingApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One teammate's sign-off on putting a listing on eBay.
 *
 * @property int $id
 * @property int $ebay_listing_id
 * @property int $user_id
 * @property ApprovalDecision $decision
 * @property string|null $note
 * @property CarbonImmutable|null $decided_at
 * @property-read EbayListing $listing
 * @property-read User $user
 */
#[Fillable(['ebay_listing_id', 'user_id', 'decision', 'note', 'decided_at'])]
class ListingApproval extends Model
{
    /** @use HasFactory<ListingApprovalFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' => ApprovalDecision::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EbayListing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(EbayListing::class, 'ebay_listing_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
