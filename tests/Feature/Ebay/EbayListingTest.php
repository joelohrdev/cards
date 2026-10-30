<?php

use App\Enums\Grader;
use App\Enums\ItemStatus;
use App\Enums\ListingStatus;
use App\Models\CollectionItem;
use App\Models\EbayAccount;
use App\Models\EbayListing;
use App\Models\ItemImage;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Http::preventStrayRequests();

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
 * A seller ready to list, with a photographed PSA 10 card.
 *
 * @return array{0: User, 1: CollectionItem}
 */
function sellerWithGradedCard(): array
{
    $account = EbayAccount::factory()->readyToList()->create();
    $item = CollectionItem::factory()->for($account->user)->graded(Grader::Psa, '10')->create(['cert_number' => '12345678']);
    ItemImage::factory()->for($item)->create(['path' => 'cards/front.jpg']);

    return [$account->user, $item];
}

function fakeSuccessfulPublish(): void
{
    Http::fake([
        'api.sandbox.ebay.com/sell/inventory/v1/inventory_item/*' => Http::response(null, 204),
        'api.sandbox.ebay.com/sell/inventory/v1/offer?*' => Http::response(['offers' => []], 404),
        'api.sandbox.ebay.com/sell/inventory/v1/offer' => Http::response(['offerId' => 'offer-1'], 201),
        'api.sandbox.ebay.com/sell/inventory/v1/offer/offer-1/publish' => Http::response(['listingId' => '110001']),
    ]);
}

test('the listing page is prefilled from the card', function () {
    [$user, $item] = sellerWithGradedCard();
    $item->update(['estimated_value' => 12550]);

    $this->actingAs($user)
        ->get(route('ebay.items.listing', $item))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ebay/listing')
            ->where('listing', null)
            ->where('defaults.price', '125.50')
            ->where('defaults.title', fn (string $title) => mb_strlen($title) <= 80 && str_contains($title, 'PSA 10'))
            ->where('aspects.Professional Grader', ['PSA'])
            ->where('aspects.Certification Number', ['12345678'])
            ->where('photos', ['https://cards.example.com/storage/cards/front.jpg'])
            ->where('warnings', [])
            ->where('ready', true));
});

test('the listing page warns when the card has no photos', function () {
    [$user, $item] = sellerWithGradedCard();
    $item->images()->delete();

    $this->actingAs($user)
        ->get(route('ebay.items.listing', $item))
        ->assertInertia(fn (Assert $page) => $page->has('warnings', 1));
});

test('publishing a new listing creates the inventory item and offer then marks the card listed', function () {
    [$user, $item] = sellerWithGradedCard();
    fakeSuccessfulPublish();

    $this->actingAs($user)
        ->post(route('ebay.items.listing.store', $item), [
            'title' => '2018 Panini Prizm Shohei Ohtani PSA 10',
            'description' => "Great card.\nShips fast.",
            'price' => '$1,250.00',
            'publish' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('ebay.items.listing', $item));

    $listing = EbayListing::query()->sole();
    expect($listing->status)->toBe(ListingStatus::Active)
        ->and($listing->sku)->toBe('CARD-'.$item->id)
        ->and($listing->offer_id)->toBe('offer-1')
        ->and($listing->listing_id)->toBe('110001')
        ->and($listing->price)->toBe(125000)
        ->and($listing->user_id)->toBe($user->id)
        ->and($listing->error)->toBeNull()
        ->and($listing->url())->toBe('https://sandbox.ebay.com/itm/110001')
        ->and($item->fresh()->status)->toBe(ItemStatus::Listed);

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/inventory_item/CARD-'.$item->id)
        && $request->hasHeader('Content-Language', 'en-US')
        && $request['condition'] === 'LIKE_NEW'
        && $request['conditionDescriptors'] === [
            ['name' => '27501', 'values' => ['275010']],
            ['name' => '27502', 'values' => ['275020']],
            ['name' => '27503', 'additionalInfo' => '12345678'],
        ]
        && $request['product']['imageUrls'] === ['https://cards.example.com/storage/cards/front.jpg']
        && $request['product']['description'] === "Great card.<br>\nShips fast.");

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://api.sandbox.ebay.com/sell/inventory/v1/offer'
        && $request['categoryId'] === '261328'
        && $request['format'] === 'FIXED_PRICE'
        && $request['pricingSummary']['price'] === ['value' => '1250.00', 'currency' => 'USD']
        && $request['merchantLocationKey'] === 'cards-default'
        && $request['listingPolicies']['returnPolicyId'] === 'return-1');
});

test('an ungraded card is listed with the card condition descriptor', function () {
    [$user, $item] = sellerWithGradedCard();
    $item->update(['is_graded' => false, 'grader' => null, 'grade' => null, 'cert_number' => null, 'raw_condition' => 'ex-mt']);
    fakeSuccessfulPublish();

    $this->actingAs($user)
        ->post(route('ebay.items.listing.store', $item), ['title' => 'Card', 'description' => 'Card', 'price' => '5', 'publish' => true])
        ->assertSessionHasNoErrors();

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && $request['condition'] === 'USED_VERY_GOOD'
        && $request['conditionDescriptors'] === [['name' => '40001', 'values' => ['400011']]]);
});

test('a rejected publish keeps the listing as a draft with eBay’s error', function () {
    [$user, $item] = sellerWithGradedCard();
    Http::fake([
        'api.sandbox.ebay.com/sell/inventory/v1/inventory_item/*' => Http::response(null, 204),
        'api.sandbox.ebay.com/sell/inventory/v1/offer?*' => Http::response(['offers' => []], 404),
        'api.sandbox.ebay.com/sell/inventory/v1/offer' => Http::response(['offerId' => 'offer-1'], 201),
        'api.sandbox.ebay.com/sell/inventory/v1/offer/offer-1/publish' => Http::response([
            'errors' => [['errorId' => 25007, 'message' => 'Invalid fulfillment policy', 'longMessage' => 'The shipping policy is not valid for this category.']],
        ], 400),
    ]);

    $this->actingAs($user)
        ->post(route('ebay.items.listing.store', $item), ['title' => 'Card', 'description' => 'Card', 'price' => '10', 'publish' => true])
        ->assertRedirect(route('ebay.items.listing', $item));

    $listing = EbayListing::query()->sole();
    expect($listing->status)->toBe(ListingStatus::Draft)
        ->and($listing->error)->toBe('The shipping policy is not valid for this category.')
        ->and($listing->offer_id)->toBe('offer-1')
        ->and($item->fresh()->status)->toBe(ItemStatus::Owned);
});

test('publishing a card without photos fails before contacting eBay', function () {
    [$user, $item] = sellerWithGradedCard();
    $item->images()->delete();

    $this->actingAs($user)
        ->post(route('ebay.items.listing.store', $item), ['title' => 'Card', 'description' => 'Card', 'price' => '10', 'publish' => true]);

    expect(EbayListing::query()->sole()->error)->toContain('photo');
    Http::assertNothingSent();
});

test('saving a draft does not contact eBay', function () {
    [$user, $item] = sellerWithGradedCard();

    $this->actingAs($user)
        ->post(route('ebay.items.listing.store', $item), ['title' => 'Card', 'description' => 'Card', 'price' => '10', 'publish' => false])
        ->assertSessionHasNoErrors();

    expect(EbayListing::query()->sole()->status)->toBe(ListingStatus::Draft);
    Http::assertNothingSent();
});

test('titles longer than 80 characters are rejected', function () {
    [$user, $item] = sellerWithGradedCard();

    $this->actingAs($user)
        ->post(route('ebay.items.listing.store', $item), ['title' => str_repeat('a', 81), 'description' => 'Card', 'price' => '10'])
        ->assertSessionHasErrors('title');
});

test('changing the price of a live listing revises the offer on eBay', function () {
    [$user, $item] = sellerWithGradedCard();
    $listing = EbayListing::factory()->for($user)->for($item)->create([
        'status' => ListingStatus::Active, 'offer_id' => 'offer-9', 'listing_id' => '110009', 'price' => 1000,
    ]);
    Http::fake([
        'api.sandbox.ebay.com/sell/inventory/v1/inventory_item/*' => Http::response(null, 204),
        'api.sandbox.ebay.com/sell/inventory/v1/offer/offer-9' => Http::response(null, 204),
    ]);

    $this->actingAs($user)
        ->patch(route('ebay.listings.update', $listing), ['title' => 'New title', 'description' => 'Card', 'price' => '15.00'])
        ->assertSessionHasNoErrors();

    expect($listing->fresh()->price)->toBe(1500)
        ->and($listing->fresh()->title)->toBe('New title')
        ->and($listing->fresh()->status)->toBe(ListingStatus::Active);
    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/offer/offer-9')
        && $request['pricingSummary']['price']['value'] === '15.00');
});

test('ending a live listing withdraws the offer and returns the card to owned', function () {
    [$user, $item] = sellerWithGradedCard();
    $item->update(['status' => ItemStatus::Listed]);
    $listing = EbayListing::factory()->for($user)->for($item)->create([
        'status' => ListingStatus::Active, 'offer_id' => 'offer-9', 'listing_id' => '110009',
    ]);
    Http::fake(['api.sandbox.ebay.com/sell/inventory/v1/offer/offer-9/withdraw' => Http::response(['listingId' => '110009'])]);

    $this->actingAs($user)
        ->post(route('ebay.listings.end', $listing))
        ->assertSessionHasNoErrors();

    expect($listing->fresh()->status)->toBe(ListingStatus::Ended)
        ->and($listing->fresh()->ended_at)->not->toBeNull()
        ->and($item->fresh()->status)->toBe(ItemStatus::Owned);
    Http::assertSentCount(1);
});

test('the listings index shows every listing, whoever made it', function () {
    [$user, $item] = sellerWithGradedCard();
    EbayListing::factory()->for($user)->for($item)->create(['status' => ListingStatus::Active]);
    EbayListing::factory()->create();

    $this->actingAs($user)
        ->get(route('ebay.listings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ebay/listings')
            ->has('listings.data', 2));
});

test('a teammate can end a listing through the eBay account it was published with', function () {
    [$seller, $item] = sellerWithGradedCard();
    $item->update(['status' => ItemStatus::Listed]);
    $listing = EbayListing::factory()->for($seller)->for($item)->create([
        'status' => ListingStatus::Active, 'offer_id' => 'offer-9', 'listing_id' => '110009',
    ]);
    Http::fake(['api.sandbox.ebay.com/sell/inventory/v1/offer/offer-9/withdraw' => Http::response(['listingId' => '110009'])]);

    $this->actingAs(User::factory()->create())
        ->post(route('ebay.listings.end', $listing))
        ->assertSessionHasNoErrors();

    expect($listing->fresh()->status)->toBe(ListingStatus::Ended)
        ->and($item->fresh()->status)->toBe(ItemStatus::Owned);
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer '.$seller->ebayAccount->access_token));
});
