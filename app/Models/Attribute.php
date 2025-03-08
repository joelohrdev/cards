<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attribute extends Model
{
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'condition' => 'string',
            'type' => 'string',
            'serial_number' => 'string',
            'grading' => 'string',
        ];
    }
}
