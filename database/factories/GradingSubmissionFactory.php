<?php

namespace Database\Factories;

use App\Enums\Grader;
use App\Enums\GradingStatus;
use App\Models\GradingSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradingSubmission>
 */
class GradingSubmissionFactory extends Factory
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
            'grader' => Grader::Psa,
            'service_level' => 'Value',
            'status' => GradingStatus::Preparing,
        ];
    }
}
