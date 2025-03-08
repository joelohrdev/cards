<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collection extends Model
{
    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'purchase_date' => 'date',
            'sold_date' => 'date',
        ];
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }
}
