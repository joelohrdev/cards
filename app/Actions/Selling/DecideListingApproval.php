<?php

namespace App\Actions\Selling;

use App\Enums\ApprovalDecision;
use App\Enums\ListingStatus;
use App\Models\EbayListing;
use App\Models\User;
use App\Notifications\ListingApprovalDeclined;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Record one teammate's approval or rejection. A single rejection sends the listing back to
 * draft; the last approval publishes it.
 */
class DecideListingApproval
{
    public function __construct(private PublishApprovedListings $publishApprovedListings) {}

    public function handle(User $approver, EbayListing $listing, bool $approve, ?string $note = null): void
    {
        $approval = $listing->approvals()
            ->where('user_id', $approver->id)
            ->where('decision', ApprovalDecision::Pending->value)
            ->first();

        if ($listing->status !== ListingStatus::PendingApproval || $approval === null) {
            throw ValidationException::withMessages(['ebay' => 'This listing isn’t waiting on your approval.']);
        }

        DB::transaction(function () use ($listing, $approval, $approve, $note): void {
            $approval->update([
                'decision' => $approve ? ApprovalDecision::Approved : ApprovalDecision::Rejected,
                'note' => $note,
                'decided_at' => now(),
            ]);

            if (! $approve) {
                $listing->forceFill(['status' => ListingStatus::Draft, 'approved_at' => null])->save();
            }
        });

        if (! $approve) {
            $listing->requester?->notify(new ListingApprovalDeclined($listing, $approver, $note));

            return;
        }

        $this->publishApprovedListings->handle($listing);
    }
}
