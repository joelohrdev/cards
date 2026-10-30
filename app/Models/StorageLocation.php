<?php

namespace App\Models;

use App\Enums\LocationType;
use Database\Factories\StorageLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $parent_id
 * @property string $name
 * @property LocationType $type
 * @property string|null $code
 * @property string|null $notes
 * @property-read StorageLocation|null $parent
 */
#[Fillable(['parent_id', 'name', 'type', 'code', 'notes'])]
class StorageLocation extends Model
{
    /** @use HasFactory<StorageLocationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LocationType::class,
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
     * @return BelongsTo<StorageLocation, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'parent_id');
    }

    /**
     * @return HasMany<StorageLocation, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(StorageLocation::class, 'parent_id');
    }

    /**
     * @return HasMany<CollectionItem, $this>
     */
    public function collectionItems(): HasMany
    {
        return $this->hasMany(CollectionItem::class);
    }

    /**
     * The breadcrumb path, e.g. "Closet Shelf / Box 4 / Row 2".
     */
    public function path(): string
    {
        $names = [$this->name];
        $parent = $this->parent;
        $depth = 0;

        while ($parent !== null && $depth < 10) {
            array_unshift($names, $parent->name);
            $parent = $parent->parent;
            $depth++;
        }

        return implode(' / ', $names);
    }
}
