<?php

namespace Database\Factories;

use App\Enums\LocationType;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorageLocation>
 */
class StorageLocationFactory extends Factory
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
            'name' => 'Box '.fake()->numberBetween(1, 50),
            'type' => LocationType::Box,
        ];
    }
}
