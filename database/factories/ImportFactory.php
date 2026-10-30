<?php

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
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
            'original_filename' => 'cards.csv',
            'path' => 'imports/'.fake()->uuid().'.csv',
            'headers' => ['Year', 'Set', 'Number', 'Player'],
            'status' => ImportStatus::Pending,
        ];
    }
}
