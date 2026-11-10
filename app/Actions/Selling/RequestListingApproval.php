<?php

namespace App\Actions\Selling;

use App\Enums\ApprovalDecision;
use App\Enums\ListingStatus;
use App\Enums\UserRole;
use App\Models\EbayListing;
use App\Models\User;
use App\Notifications\ListingApprovalRequested;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Ask the other users to approve putting a draft on eBay. Admins are never asked, and
 * nobody approves their own listing. With nobody left to ask, it is published straight away.
 */
class RequestListingApproval
{
    public function __construct(private PublishApprovedListings $publishApprovedListings) {}

    public function handle(User $requester, EbayListing $listing): void
    {
        if ($listing->status !== ListingStatus::Draft) {
            throw ValidationException::withMessages(['ebay' => 'Only drafts can be sent for approval.']);
        }

        $approvers = User::query()
            ->whereKeyNot($requester->id)
            ->where('role', '!=', UserRole::Admin->value)
            ->get();

        DB::transaction(function () use ($requester, $listing, $approvers): void {
            $listing->approvals()->delete();

            $listing->forceFill([
                'status' => ListingStatus::PendingApproval,
                'approval_requested_by' => $requester->id,
                'approval_requested_at' => now(),
                'approved_at' => null,
                'error' => null,
            ])->save();

            $listing->approvals()->createMany($approvers->map(fn (User $approver): array => [
                'user_id' => $approver->id,
                'decision' => ApprovalDecision::Pending,
            ])->all());
        });

        if ($approvers->isEmpty()) {
            $this->publishApprovedListings->handle($listing);

            return;
        }

        Notification::send($approvers, new ListingApprovalRequested($listing, $requester));
    }
}
