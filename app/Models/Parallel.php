<?php

namespace App\Models;

use Database\Factories\ParallelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $card_set_id
 * @property string $name
 * @property int|null $print_run
 */
#[Fillable(['card_set_id', 'name', 'print_run'])]
class Parallel extends Model
{
    /** @use HasFactory<ParallelFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
}
