<?php

namespace Database\Factories;

use App\Enums\ItemStatus;
use App\Enums\SalePlatform;
use App\Models\CollectionItem;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
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
            'collection_item_id' => fn (array $attributes) => CollectionItem::factory()->status(ItemStatus::Sold)->state(['user_id' => $attributes['user_id']]),
            'platform' => SalePlatform::Ebay,
            'sold_on' => fake()->dateTimeBetween('-1 year'),
            'sale_price' => fake()->numberBetween(500, 50000),
            'fees' => fake()->numberBetween(0, 2000),
            'shipping_cost' => fake()->numberBetween(0, 500),
        ];
    }
}
