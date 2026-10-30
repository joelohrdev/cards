<?php

namespace Database\Factories;

use App\Enums\ImageSide;
use App\Models\CollectionItem;
use App\Models\ItemImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemImage>
 */
class ItemImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'collection_item_id' => CollectionItem::factory(),
            'path' => 'cards/'.fake()->uuid().'.jpg',
            'side' => ImageSide::Front,
            'position' => 0,
        ];
    }
}
