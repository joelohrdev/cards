<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\PriceComp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceComp>
 */
class PriceCompFactory extends Factory
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
            'card_id' => Card::factory(),
            'price' => fake()->numberBetween(100, 50000),
            'sold_on' => fake()->dateTimeBetween('-6 months'),
            'source' => 'eBay',
        ];
    }
}
