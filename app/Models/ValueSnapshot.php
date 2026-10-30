<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ValueSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property CarbonImmutable $captured_on
 * @property int $item_count
 * @property int $total_value
 * @property int $total_cost
 */
#[Fillable(['captured_on', 'item_count', 'total_value', 'total_cost'])]
class ValueSnapshot extends Model
{
    /** @use HasFactory<ValueSnapshotFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'captured_on' => 'date',
            'item_count' => 'integer',
            'total_value' => 'integer',
            'total_cost' => 'integer',
        ];
    }
}
