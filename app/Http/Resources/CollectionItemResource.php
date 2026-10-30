<?php

namespace App\Http\Resources;

use App\Models\CollectionItem;
use App\Models\ItemImage;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CollectionItem
 */
class CollectionItemResource extends JsonResource
{
    /**
     * Relations needed to render an item in lists and grids.
     *
     * @var list<string>
     */
    public const array LIST_RELATIONS = [
        'card.cardSet.manufacturer',
        'card.team',
        'card.players',
        'parallel',
        'tags',
        'frontImage',
        'storageLocation.parent.parent',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $printRun = $this->parallel->print_run ?? $this->card->print_run;

        return [
            'id' => $this->id,
            'title' => $this->title(),
            'card' => CardResource::make($this->card)->resolve(),
            'parallel' => $this->parallel ? [
                'id' => $this->parallel->id,
                'name' => $this->parallel->name,
                'print_run' => $this->parallel->print_run,
            ] : null,
            'print_run' => $printRun,
            'serial_number' => $this->serial_number,
            'is_graded' => $this->is_graded,
            'grader' => $this->grader?->value,
            'grade' => $this->grade,
            'cert_number' => $this->cert_number,
            'raw_condition' => $this->raw_condition?->value,
            'condition_label' => $this->conditionLabel(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'purchase_price' => $this->purchase_price,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'purchase_source' => $this->purchase_source,
            'estimated_value' => $this->estimated_value,
            'value_updated_at' => $this->value_updated_at?->toIso8601String(),
            'location' => $this->storageLocation ? [
                'id' => $this->storageLocation->id,
                'name' => $this->storageLocation->name,
                'path' => $this->storageLocation->path(),
            ] : null,
            'storage_position' => $this->storage_position,
            'notes' => $this->notes,
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ])->values()),
            'image' => $this->whenLoaded('frontImage', fn () => $this->frontImage?->url),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn (ItemImage $image): array => [
                'id' => $image->id,
                'url' => $image->url,
                'side' => $image->side->value,
            ])->values()),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
