<?php

namespace Database\Factories;

use App\Enums\Sport;
use App\Models\CardSet;
use App\Models\Manufacturer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardSet>
 */
class CardSetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'manufacturer_id' => Manufacturer::factory(),
            'sport' => fake()->randomElement(Sport::cases()),
            'year' => fake()->numberBetween(1952, 2026),
            'name' => fake()->unique()->words(2, true),
            'card_count' => fake()->numberBetween(100, 700),
        ];
    }
}
