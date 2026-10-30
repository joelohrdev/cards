<?php

namespace App\Models;

use App\Enums\ImageSide;
use Database\Factories\ItemImageFactory;
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
 * @property ImageSide $side
 * @property int $position
 * @property-read string $url
 */
#[Fillable(['path', 'side', 'position'])]
#[Appends(['url'])]
class ItemImage extends Model
{
    /** @use HasFactory<ItemImageFactory> */
    use HasFactory;

    public const string DISK = 'public';

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
        return Attribute::get(fn (): string => Storage::disk(self::DISK)->url($this->path));
    }
}
