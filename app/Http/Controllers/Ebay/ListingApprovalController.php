<?php

namespace App\Http\Controllers\Ebay;

use App\Actions\Selling\DecideListingApproval;
use App\Actions\Selling\RequestListingApproval;
use App\Enums\ApprovalDecision;
use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CollectionItemResource;
use App\Models\EbayListing;
use App\Models\ListingApproval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ListingApprovalController extends Controller
{
    /**
     * Listings waiting on approval, with the ones waiting on the signed-in user first.
     */
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $listings = EbayListing::query()
            ->where('status', ListingStatus::PendingApproval->value)
            ->with([
                'requester',
                'approvals.user',
                ...collect(CollectionItemResource::LIST_RELATIONS)->map(fn (string $relation): string => 'collectionItem.'.$relation)->all(),
            ])
            ->oldest('approval_requested_at')
            ->get()
            ->map(fn (EbayListing $listing): array => [
                'id' => $listing->id,
                'collection_item_id' => $listing->collection_item_id,
                'title' => $listing->title,
                'price' => $listing->price,
                'item' => CollectionItemResource::make($listing->collectionItem)->resolve(),
                ...self::approvalData($listing, $userId, $request->user()->isAdmin()),
            ])
            ->sortByDesc('can_decide')
            ->values();

        return Inertia::render('ebay/approvals', ['listings' => $listings]);
    }

    /**
     * Ask the rest of the team to approve a draft.
     */
    public function store(Request $request, EbayListing $listing, RequestListingApproval $requestApproval): RedirectResponse
    {
        $requestApproval->handle($request->user(), $listing);

        $listing->refresh();

        Inertia::flash('toast', match (true) {
            $listing->status === ListingStatus::PendingApproval => ['type' => 'success', 'message' => 'Sent for approval. It goes to eBay once everyone approves.'],
            $listing->status === ListingStatus::Active => ['type' => 'success', 'message' => 'Listed on eBay.'],
            default => ['type' => 'error', 'message' => 'eBay didn’t accept the listing. It’s saved as a draft — see the error on the page.'],
        });

        return back();
    }

    /**
     * Take a listing back out of approval so it can be changed.
     */
    public function destroy(Request $request, EbayListing $listing): RedirectResponse
    {
        $user = $request->user();

        if ($listing->status !== ListingStatus::PendingApproval) {
            throw ValidationException::withMessages(['ebay' => 'This listing isn’t waiting on approval.']);
        }

        if ($listing->approval_requested_by !== $user->id && ! $user->isAdmin()) {
            throw ValidationException::withMessages(['ebay' => 'Only the person who asked, or an admin, can withdraw this request.']);
        }

        $listing->approvals()->delete();
        $listing->forceFill(['status' => ListingStatus::Draft, 'approval_requested_by' => null, 'approval_requested_at' => null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Approval request withdrawn. The listing is a draft again.']);

        return back();
    }

    /**
     * Approve or decline a listing.
     */
    public function decide(Request $request, EbayListing $listing, DecideListingApproval $decideApproval): RedirectResponse
    {
        $validated = $request->validate([
            'approve' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $decideApproval->handle($request->user(), $listing, (bool) $validated['approve'], $validated['note'] ?? null);

        $listing->refresh();

        Inertia::flash('toast', match (true) {
            ! $validated['approve'] => ['type' => 'success', 'message' => 'Declined. The listing is back to a draft.'],
            $listing->status === ListingStatus::Active => ['type' => 'success', 'message' => 'Approved — that was the last one, so it’s now live on eBay.'],
            $listing->status === ListingStatus::PendingApproval => ['type' => 'success', 'message' => 'Approved. Still waiting on others.'],
            default => ['type' => 'error', 'message' => 'Everyone approved, but eBay didn’t accept the listing. It’s saved as a draft.'],
        });

        return back();
    }

    /**
     * Who asked, who has signed off, and what the signed-in user can do.
     *
     * @return array{requested_by: string|null, requested_at: string|null, approved_at: string|null, approvals: list<array{name: string, decision: string, note: string|null, decided_at: string|null}>, can_decide: bool, can_withdraw: bool}
     */
    public static function approvalData(EbayListing $listing, int $userId, bool $isAdmin = false): array
    {
        $pending = $listing->status === ListingStatus::PendingApproval;

        return [
            'requested_by' => $listing->requester?->name,
            'requested_at' => $listing->approval_requested_at?->toIso8601String(),
            'approved_at' => $listing->approved_at?->toIso8601String(),
            'approvals' => array_values($listing->approvals->map(fn (ListingApproval $approval): array => [
                'name' => $approval->user->name,
                'decision' => $approval->decision->value,
                'note' => $approval->note,
                'decided_at' => $approval->decided_at?->toIso8601String(),
            ])->all()),
            'can_decide' => $pending && $listing->approvals->contains(
                fn (ListingApproval $approval): bool => $approval->user_id === $userId && $approval->decision === ApprovalDecision::Pending,
            ),
            'can_withdraw' => $pending && ($listing->approval_requested_by === $userId || $isAdmin),
        ];
    }
}
