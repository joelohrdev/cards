<?php

namespace App\Models;

use Database\Factories\WantListEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int $card_id
 * @property int|null $parallel_id
 * @property int|null $max_price
 * @property int $priority
 * @property string|null $notes
 */
#[Fillable(['user_id', 'card_id', 'parallel_id', 'max_price', 'priority', 'notes'])]
class WantListEntry extends Model
{
    /** @use HasFactory<WantListEntryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_price' => 'integer',
            'priority' => 'integer',
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
}
