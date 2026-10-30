<?php

use App\Enums\ItemStatus;
use App\Enums\ListingStatus;
use App\Enums\SalePlatform;
use App\Models\CollectionItem;
use App\Models\EbayAccount;
use App\Models\EbayListing;
use App\Models\Sale;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'services.ebay.client_id' => 'client-id',
        'services.ebay.client_secret' => 'client-secret',
        'services.ebay.ru_name' => 'Cards-RuName',
        'services.ebay.api_url' => 'https://api.sandbox.ebay.com',
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function ebayOrder(string $sku, array $overrides = []): array
{
    return [
        'orderId' => '12-34567-89012',
        'creationDate' => '2026-10-20T15:04:05.000Z',
        'orderPaymentStatus' => 'PAID',
        'cancelStatus' => ['cancelState' => 'NONE_REQUESTED'],
        'buyer' => ['username' => 'happy_buyer'],
        'totalMarketplaceFee' => ['value' => '13.25', 'currency' => 'USD'],
        'lineItems' => [[
            'lineItemId' => '1000',
            'sku' => $sku,
            'lineItemCost' => ['value' => '100.00', 'currency' => 'USD'],
            'deliveryCost' => ['shippingCost' => ['value' => '4.99', 'currency' => 'USD']],
        ]],
        ...$overrides,
    ];
}

test('paid orders become sales and mark the card and listing sold, once', function () {
    $account = EbayAccount::factory()->readyToList()->create();
    $item = CollectionItem::factory()->for($account->user)->status(ItemStatus::Listed)->create();
    $listing = EbayListing::factory()->for($account->user)->for($item)->create(['sku' => 'CARD-'.$item->id, 'status' => ListingStatus::Active]);
    Http::fake(['api.sandbox.ebay.com/sell/fulfillment/v1/order*' => Http::response(['orders' => [ebayOrder('CARD-'.$item->id)], 'total' => 1])]);

    $this->artisan('cards:sync-ebay-orders')->assertSuccessful();
    $this->artisan('cards:sync-ebay-orders')->assertSuccessful();

    $sale = Sale::query()->sole();
    expect($sale->user_id)->toBe($account->user_id)
        ->and($sale->collection_item_id)->toBe($item->id)
        ->and($sale->platform)->toBe(SalePlatform::Ebay)
        ->and($sale->sale_price)->toBe(10000)
        ->and($sale->fees)->toBe(1325)
        ->and($sale->external_order_id)->toBe('12-34567-89012')
        ->and($sale->buyer)->toBe('happy_buyer')
        ->and($sale->sold_on->toDateString())->toBe('2026-10-20')
        ->and($item->fresh()->status)->toBe(ItemStatus::Sold)
        ->and($listing->fresh()->status)->toBe(ListingStatus::Sold)
        ->and($account->fresh()->orders_synced_at)->not->toBeNull();
});

test('unpaid and cancelled orders and unknown SKUs are ignored', function () {
    $account = EbayAccount::factory()->create();
    $item = CollectionItem::factory()->for($account->user)->status(ItemStatus::Listed)->create();
    EbayListing::factory()->for($account->user)->for($item)->create(['sku' => 'CARD-'.$item->id, 'status' => ListingStatus::Active]);
    Http::fake(['api.sandbox.ebay.com/sell/fulfillment/v1/order*' => Http::response(['orders' => [
        ebayOrder('CARD-'.$item->id, ['orderPaymentStatus' => 'PENDING']),
        ebayOrder('CARD-'.$item->id, ['cancelStatus' => ['cancelState' => 'CANCELED']]),
        ebayOrder('CARD-999999'),
    ]])]);

    $this->artisan('cards:sync-ebay-orders')->assertSuccessful();

    expect(Sale::query()->exists())->toBeFalse()
        ->and($item->fresh()->status)->toBe(ItemStatus::Listed);
});

test('orders for another user’s SKU never create a sale for that user', function () {
    $seller = EbayAccount::factory()->create();
    $otherItem = CollectionItem::factory()->status(ItemStatus::Listed)->create();
    EbayListing::factory()->for($otherItem->user)->for($otherItem)->create(['sku' => 'CARD-'.$otherItem->id, 'status' => ListingStatus::Active]);
    Http::fake(['api.sandbox.ebay.com/sell/fulfillment/v1/order*' => Http::response(['orders' => [ebayOrder('CARD-'.$otherItem->id)]])]);

    $this->artisan('cards:sync-ebay-orders')->assertSuccessful();

    expect(Sale::query()->exists())->toBeFalse();
    expect($seller->fresh()->orders_synced_at)->not->toBeNull();
});
