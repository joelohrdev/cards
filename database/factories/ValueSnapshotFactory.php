<?php

namespace Database\Factories;

use App\Models\ValueSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ValueSnapshot>
 */
class ValueSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'captured_on' => fake()->unique()->dateTimeBetween('-1 year'),
            'item_count' => fake()->numberBetween(1, 5000),
            'total_value' => fake()->numberBetween(1000, 10000000),
            'total_cost' => fake()->numberBetween(1000, 10000000),
        ];
    }
}
