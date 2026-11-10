<?php

use App\Enums\ApprovalDecision;
use App\Enums\Grader;
use App\Enums\ItemStatus;
use App\Enums\ListingStatus;
use App\Enums\UserRole;
use App\Models\CollectionItem;
use App\Models\EbayAccount;
use App\Models\EbayListing;
use App\Models\ItemImage;
use App\Models\ListingApproval;
use App\Models\User;
use App\Notifications\ListingApprovalCompleted;
use App\Notifications\ListingApprovalDeclined;
use App\Notifications\ListingApprovalRequested;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Http::preventStrayRequests();
    Notification::fake();

    config([
        'services.ebay.client_id' => 'client-id',
        'services.ebay.client_secret' => 'client-secret',
        'services.ebay.ru_name' => 'Cards-RuName',
        'services.ebay.api_url' => 'https://api.sandbox.ebay.com',
        'services.ebay.web_url' => 'https://sandbox.ebay.com',
        'services.ebay.marketplace_id' => 'EBAY_US',
        'filesystems.disks.public.url' => 'https://cards.example.com/storage',
    ]);
});

/**
 * A seller ready to list a photographed card as a saved draft.
 *
 * @return array{0: User, 1: EbayListing}
 */
function sellerWithDraft(): array
{
    $account = EbayAccount::factory()->readyToList()->create();
    $item = CollectionItem::factory()->for($account->user)->graded(Grader::Psa, '10')->create(['cert_number' => '12345678']);
    ItemImage::factory()->for($item)->create(['path' => 'cards/front.jpg']);
    $listing = EbayListing::factory()->for($account->user)->for($item)->create(['sku' => 'CARD-'.$item->id, 'price' => 5000]);

    return [$account->user, $listing];
}

function fakeEbayPublish(): void
{
    Http::fake([
        'api.sandbox.ebay.com/sell/inventory/v1/inventory_item/*' => Http::response(null, 204),
        'api.sandbox.ebay.com/sell/inventory/v1/offer?*' => Http::response(['offers' => []], 404),
        'api.sandbox.ebay.com/sell/inventory/v1/offer' => Http::response(['offerId' => 'offer-1'], 201),
        'api.sandbox.ebay.com/sell/inventory/v1/offer/offer-1/publish' => Http::response(['listingId' => '110001']),
    ]);
}

test('sending a draft for approval asks everyone else and holds it back from eBay', function () {
    [$seller, $listing] = sellerWithDraft();
    $teammates = User::factory()->count(2)->create();

    $this->actingAs($seller)
        ->post(route('ebay.listings.approval.store', $listing))
        ->assertSessionHasNoErrors();

    $listing->refresh();

    expect($listing->status)->toBe(ListingStatus::PendingApproval)
        ->and($listing->approval_requested_by)->toBe($seller->id)
        ->and($listing->approvals()->pluck('user_id')->sort()->values()->all())->toBe($teammates->pluck('id')->all())
        ->and($listing->collectionItem->fresh()->status)->toBe(ItemStatus::Owned);
    Notification::assertSentTo($teammates, ListingApprovalRequested::class);
    Notification::assertNotSentTo($seller, ListingApprovalRequested::class);
    Http::assertNothingSent();
});

test('admins are never asked to approve a listing', function () {
    [$seller, $listing] = sellerWithDraft();
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();

    $this->actingAs($seller)->post(route('ebay.listings.approval.store', $listing))->assertSessionHasNoErrors();

    expect($listing->approvals()->pluck('user_id')->all())->toBe([$member->id]);
    Notification::assertNotSentTo($admin, ListingApprovalRequested::class);
});

test('an admin’s own listing still needs every member to approve', function () {
    [$seller, $listing] = sellerWithDraft();
    $seller->forceFill(['role' => UserRole::Admin])->save();
    $members = User::factory()->count(2)->create();

    $this->actingAs($seller)->post(route('ebay.listings.approval.store', $listing))->assertSessionHasNoErrors();

    expect($listing->fresh()->status)->toBe(ListingStatus::PendingApproval)
        ->and($listing->approvals()->pluck('user_id')->sort()->values()->all())->toBe($members->pluck('id')->all());
});

test('a member’s listing goes straight to eBay when the only other person is an admin', function () {
    [$seller, $listing] = sellerWithDraft();
    User::factory()->admin()->create();
    fakeEbayPublish();

    $this->actingAs($seller)->post(route('ebay.listings.approval.store', $listing))->assertSessionHasNoErrors();

    expect($listing->fresh()->status)->toBe(ListingStatus::Active);
});

test('promoting the last member a listing waits on to admin publishes it', function () {
    [$seller, $listing] = sellerWithDraft();
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();
    fakeEbayPublish();
    $this->actingAs($seller)->post(route('ebay.listings.approval.store', $listing));

    $this->actingAs($admin)
        ->patch(route('team.members.update', $member), ['role' => UserRole::Admin->value])
        ->assertRedirect(route('team.index'));

    expect($listing->fresh()->status)->toBe(ListingStatus::Active)
        ->and($member->listingApprovals()->exists())->toBeFalse();
});

test('choosing publish on the listing form sends it for approval when others are on the team', function () {
    [$seller, $listing] = sellerWithDraft();
    User::factory()->create();

    $this->actingAs($seller)
        ->patch(route('ebay.listings.update', $listing), ['title' => 'Card', 'description' => 'Card', 'price' => '50', 'publish' => true])
        ->assertSessionHasNoErrors();

    expect($listing->fresh()->status)->toBe(ListingStatus::PendingApproval);
    Http::assertNothingSent();
});

test('with nobody else on the team a listing goes straight to eBay', function () {
    [$seller, $listing] = sellerWithDraft();
    fakeEbayPublish();

    $this->actingAs($seller)
        ->post(route('ebay.listings.approval.store', $listing))
        ->assertSessionHasNoErrors();

    expect($listing->fresh()->status)->toBe(ListingStatus::Active)
        ->and($listing->fresh()->approved_at)->not->toBeNull();
    Notification::assertNothingSent();
});

test('a listing stays pending until the last approval, which publishes it', function () {
    [$seller, $listing] = sellerWithDraft();
    [$first, $second] = User::factory()->count(2)->create();
    fakeEbayPublish();

    $this->actingAs($seller)->post(route('ebay.listings.approval.store', $listing));

    $this->actingAs($first)
        ->post(route('ebay.listings.approval.decide', $listing), ['approve' => true])
        ->assertSessionHasNoErrors();

    expect($listing->fresh()->status)->toBe(ListingStatus::PendingApproval);
    Http::assertNothingSent();

    $this->actingAs($second)
        ->post(route('ebay.listings.approval.decide', $listing), ['approve' => true])
        ->assertSessionHasNoErrors();

    expect($listing->fresh()->status)->toBe(ListingStatus::Active)
        ->and($listing->collectionItem->fresh()->status)->toBe(ItemStatus::Listed);
    Notification::assertSentTo($seller, ListingApprovalCompleted::class, fn (ListingApprovalCompleted $notification) => $notification->published);
});

test('one decline sends the listing back to a draft and tells whoever asked', function () {
    [$seller, $listing] = sellerWithDraft();
    [$first, $second] = User::factory()->count(2)->create();

    $this->actingAs($seller)->post(route('ebay.listings.approval.store', $listing));

    $this->actingAs($first)
        ->post(route('ebay.listings.approval.decide', $listing), ['approve' => false, 'note' => 'Hold it until the show'])
        ->assertSessionHasNoErrors();

    expect($listing->fresh()->status)->toBe(ListingStatus::Draft)
        ->and($listing->approvals()->where('user_id', $first->id)->sole()->note)->toBe('Hold it until the show');

    $this->actingAs($second)
        ->post(route('ebay.listings.approval.decide', $listing), ['approve' => true])
        ->assertSessionHasErrors(['ebay' => 'This listing isn’t waiting on your approval.']);

    Notification::assertSentTo($seller, ListingApprovalDeclined::class, fn (ListingApprovalDeclined $notification) => $notification->note === 'Hold it until the show');
    Http::assertNothingSent();
});

test('the person who asked can’t approve their own listing', function () {
    [$seller, $listing] = sellerWithDraft();
    User::factory()->create();

    $this->actingAs($seller)->post(route('ebay.listings.approval.store', $listing));

    $this->actingAs($seller)
        ->post(route('ebay.listings.approval.decide', $listing), ['approve' => true])
        ->assertSessionHasErrors(['ebay' => 'This listing isn’t waiting on your approval.']);

    expect($listing->fresh()->status)->toBe(ListingStatus::PendingApproval);
});

test('a pending listing can’t be edited until the request is withdrawn', function () {
    [$seller, $listing] = sellerWithDraft();
    User::factory()->create();

    $this->actingAs($seller)->post(route('ebay.listings.approval.store', $listing));

    $this->actingAs($seller)
        ->patch(route('ebay.listings.update', $listing), ['title' => 'Cheaper', 'description' => 'Card', 'price' => '1'])
        ->assertSessionHasErrors(['title' => 'This listing is waiting on approval. Withdraw the request to make changes.']);

    $this->actingAs($seller)
        ->delete(route('ebay.listings.approval.destroy', $listing))
        ->assertSessionHasNoErrors();

    expect($listing->fresh()->status)->toBe(ListingStatus::Draft)
        ->and($listing->approvals()->count())->toBe(0);
});

test('only whoever asked, or an admin, can withdraw a request', function () {
    [$seller, $listing] = sellerWithDraft();
    $member = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($seller)->post(route('ebay.listings.approval.store', $listing));

    $this->actingAs($member)
        ->delete(route('ebay.listings.approval.destroy', $listing))
        ->assertSessionHasErrors(['ebay' => 'Only the person who asked, or an admin, can withdraw this request.']);

    expect($listing->fresh()->status)->toBe(ListingStatus::PendingApproval);

    $this->actingAs($admin)
        ->delete(route('ebay.listings.approval.destroy', $listing))
        ->assertSessionHasNoErrors();

    expect($listing->fresh()->status)->toBe(ListingStatus::Draft);
});

test('a draft can’t be published directly until everyone has approved it', function () {
    [$seller, $listing] = sellerWithDraft();

    $this->actingAs($seller)
        ->post(route('ebay.listings.publish', $listing))
        ->assertSessionHasErrors(['ebay' => 'Everyone has to approve this listing before it goes to eBay.']);

    Http::assertNothingSent();
});

test('an approved draft eBay turned away can be retried, but changing it needs approval again', function () {
    [$seller, $listing] = sellerWithDraft();
    User::factory()->create();
    $listing->update(['error' => 'Bad policy']);
    $listing->forceFill(['approved_at' => now()])->save();
    fakeEbayPublish();

    $this->actingAs($seller)
        ->post(route('ebay.listings.publish', $listing))
        ->assertSessionHasNoErrors();

    expect($listing->fresh()->status)->toBe(ListingStatus::Active);

    [$seller, $changed] = sellerWithDraft();
    $changed->forceFill(['approved_at' => now()])->save();

    $this->actingAs($seller)
        ->patch(route('ebay.listings.update', $changed), ['title' => 'New title', 'description' => 'Card', 'price' => '50', 'publish' => true])
        ->assertSessionHasNoErrors();

    expect($changed->fresh()->status)->toBe(ListingStatus::PendingApproval)
        ->and($changed->fresh()->approved_at)->toBeNull();
});

test('removing the last person who hadn’t approved publishes the listing', function () {
    [$seller, $listing] = sellerWithDraft();
    [$approver, $leaving] = User::factory()->count(2)->create();
    fakeEbayPublish();

    $this->actingAs($seller)->post(route('ebay.listings.approval.store', $listing));
    $this->actingAs($approver)->post(route('ebay.listings.approval.decide', $listing), ['approve' => true]);

    $leaving->delete();

    expect($listing->fresh()->status)->toBe(ListingStatus::Active);
});

test('the approvals page lists pending listings and the header counts the ones waiting on you', function () {
    [, $listing] = sellerWithDraft();
    $approver = User::factory()->create();
    $listing->forceFill(['status' => ListingStatus::PendingApproval, 'approval_requested_at' => now()])->save();
    ListingApproval::factory()->for($listing, 'listing')->for($approver)->create(['decision' => ApprovalDecision::Pending]);
    EbayListing::factory()->create();

    $this->actingAs($approver)
        ->get(route('ebay.approvals.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ebay/approvals')
            ->where('approvalsWaiting', 1)
            ->has('listings', 1)
            ->where('listings.0.id', $listing->id)
            ->where('listings.0.can_decide', true)
            ->where('listings.0.approvals.0.decision', 'pending'));
});
