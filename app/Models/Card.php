<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
