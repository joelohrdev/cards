<?php

namespace Database\Factories;

use App\Enums\ListingStatus;
use App\Models\CollectionItem;
use App\Models\EbayListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EbayListing>
 */
class EbayListingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'collection_item_id' => CollectionItem::factory(),
            'sku' => 'CARD-'.fake()->unique()->numberBetween(1, 9999999),
            'title' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'price' => fake()->numberBetween(100, 50000),
            'status' => ListingStatus::Draft,
        ];
    }
}
