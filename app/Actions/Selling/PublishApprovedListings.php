<?php

namespace App\Actions\Selling;

use App\Enums\ApprovalDecision;
use App\Enums\ListingStatus;
use App\Models\EbayListing;
use App\Notifications\ListingApprovalCompleted;
use App\Services\Ebay\ListingPublisher;

/**
 * Publish listings whose approval round has no outstanding or declined sign-offs left.
 */
class PublishApprovedListings
{
    public function __construct(private ListingPublisher $publisher) {}

    /**
     * Check one listing, or every listing waiting on approval (after a user is removed, say).
     */
    public function handle(?EbayListing $listing = null): void
    {
        $listings = $listing === null
            ? EbayListing::query()->where('status', ListingStatus::PendingApproval->value)->get()
            : collect([$listing]);

        foreach ($listings as $pending) {
            $this->publishIfApproved($pending);
        }
    }

    private function publishIfApproved(EbayListing $listing): void
    {
        $outstanding = $listing->approvals()
            ->where('decision', '!=', ApprovalDecision::Approved->value)
            ->exists();

        if ($listing->status !== ListingStatus::PendingApproval || $outstanding) {
            return;
        }

        $listing->forceFill(['status' => ListingStatus::Draft, 'approved_at' => now()])->save();

        $published = $this->publisher->publish($listing);

        if ($listing->approvals()->exists()) {
            $listing->requester?->notify(new ListingApprovalCompleted($listing->refresh(), $published));
        }
    }
}
