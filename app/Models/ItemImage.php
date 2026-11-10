<?php

namespace App\Models;

use App\Enums\ImageSide;
use Database\Factories\ItemImageFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $collection_item_id
 * @property string $path
 * @property string|null $thumb_path
 * @property ImageSide $side
 * @property int $position
 * @property-read string $url
 * @property-read string $thumb_url
 */
#[Fillable(['path', 'thumb_path', 'side', 'position'])]
#[Appends(['url', 'thumb_url'])]
class ItemImage extends Model
{
    /** @use HasFactory<ItemImageFactory> */
    use HasFactory;

    /**
     * The disk card photos live on: this server's public disk, or Cloudflare R2.
     */
    public static function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.card_photos'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'side' => ImageSide::class,
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CollectionItem, $this>
     */
    public function collectionItem(): BelongsTo
    {
        return $this->belongsTo(CollectionItem::class);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => self::disk()->url($this->path));
    }

    /**
     * The small version for grids, or the full photo for images stored before thumbnails existed.
     *
     * @return Attribute<string, never>
     */
    protected function thumbUrl(): Attribute
    {
        return Attribute::get(fn (): string => self::disk()->url($this->thumb_path ?? $this->path));
    }

    /**
     * Every stored file for this image, for deleting alongside the record.
     *
     * @return list<string>
     */
    public function files(): array
    {
        return array_values(array_filter([$this->path, $this->thumb_path]));
    }
}
