<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Card extends Model
{
    protected $fillable = [
        'uuid',
        'year',
        'mfg',
        'set_type',
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
            $card->uuid = (string) Str::uuid();
        });
    }

    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => number_format($value / 100, 2),
            set: fn ($value) => $value * 100,
        );
    }
}
