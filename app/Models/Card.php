<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Card extends Model
{
    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'name' => 'string',
            'team' => 'string',
            'year' => 'string',
            'brand' => 'string',
            'number' => 'string',
            'set_name' => 'string',
            'image' => 'string',
            'quantity' => 'integer',
            'estimated_value' => 'integer',
            'purchase_price' => 'integer',
            'purchase_date' => 'date',
            'is_sold' => 'boolean',
            'sold_date' => 'date',
            'favorite' => 'boolean',
        ];
    }

    public function attribute(): HasOne
    {
        return $this->hasOne(Attribute::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }
}
