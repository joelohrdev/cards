<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        ];
    }

    public function attribute(): HasOne
    {
        return $this->hasOne(Attribute::class);
    }
}
