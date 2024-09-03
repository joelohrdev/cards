<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    protected $fillable = [
        'uuid',
        'year',
        'mfg',
        'set',
        'first_name',
        'last_name',
        'team',
        'numbered',
        'rookie',
        'parallel',
        'value',
        'notes',
        'image_front',
        'image_back',
    ];

    protected $casts = [
        'rookie' => 'boolean',
        'value' => 'integer',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function ($card) {
            $card->uuid = (string) \Illuminate\Support\Str::uuid();
        });
    }
}
