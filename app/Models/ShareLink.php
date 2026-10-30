<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ShareLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A public, read-only showcase of the collection (optionally limited to one tag).
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $tag_id
 * @property string $token
 * @property string $title
 * @property string|null $description
 * @property bool $show_values
 * @property CarbonImmutable|null $expires_at
 * @property-read User|null $user
 * @property-read Tag|null $tag
 */
#[Fillable(['tag_id', 'title', 'description', 'show_values', 'expires_at'])]
class ShareLink extends Model
{
    /** @use HasFactory<ShareLinkFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (ShareLink $link): void {
            $link->token ??= Str::random(40);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'show_values' => 'boolean',
            'expires_at' => 'datetime',
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
     * @return BelongsTo<Tag, $this>
     */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
