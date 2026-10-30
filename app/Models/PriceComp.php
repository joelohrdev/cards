<?php

namespace App\Models;

use App\Enums\Grader;
use Carbon\CarbonImmutable;
use Database\Factories\PriceCompFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manually recorded sold price for a card, used to estimate values.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $card_id
 * @property int|null $parallel_id
 * @property Grader|null $grader
 * @property string|null $grade
 * @property int $price
 * @property CarbonImmutable $sold_on
 * @property string|null $source
 * @property string|null $url
 * @property string|null $notes
 */
#[Fillable(['card_id', 'parallel_id', 'grader', 'grade', 'price', 'sold_on', 'source', 'url', 'notes'])]
class PriceComp extends Model
{
    /** @use HasFactory<PriceCompFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grader' => Grader::class,
            'price' => 'integer',
            'sold_on' => 'date',
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
