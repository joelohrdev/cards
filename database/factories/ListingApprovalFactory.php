<?php

namespace Database\Factories;

use App\Enums\ApprovalDecision;
use App\Models\EbayListing;
use App\Models\ListingApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListingApproval>
 */
class ListingApprovalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ebay_listing_id' => EbayListing::factory(),
            'user_id' => User::factory(),
            'decision' => ApprovalDecision::Pending,
        ];
    }
}
