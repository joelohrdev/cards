<?php

namespace Database\Factories;

use App\Models\CardSet;
use App\Models\Parallel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Parallel>
 */
class ParallelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'card_set_id' => CardSet::factory(),
            'name' => fake()->unique()->randomElement(['Silver', 'Gold', 'Refractor', 'Red', 'Blue', 'Green', 'Orange', 'Purple', 'Black', 'Holo']).' '.fake()->unique()->numberBetween(1, 9999),
            'print_run' => fake()->optional()->randomElement([5, 10, 25, 50, 99, 199, 299]),
        ];
    }
}
