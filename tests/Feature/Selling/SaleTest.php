<?php

use App\Enums\ItemStatus;
use App\Enums\SalePlatform;
use App\Models\CollectionItem;
use App\Models\GradingSubmission;
use App\Models\Sale;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('sales.index'))->assertRedirect(route('login'));
});

test('recording a sale stores it in cents and marks the card sold', function () {
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('sales.store'), [
            'collection_item_id' => $item->id,
            'platform' => 'ebay',
            'sold_on' => '2026-03-14',
            'sale_price' => '$1,250.00',
            'fees' => '165.63',
            'shipping_cost' => '5',
            'buyer' => 'cardfan99',
        ])
        ->assertSessionHasNoErrors();

    $sale = Sale::query()->sole();

    expect($sale->user_id)->toBe($user->id)
        ->and($sale->platform)->toBe(SalePlatform::Ebay)
        ->and($sale->sale_price)->toBe(125000)
        ->and($sale->fees)->toBe(16563)
        ->and($sale->shipping_cost)->toBe(500)
        ->and($sale->netProceeds())->toBe(125000 - 16563 - 500)
        ->and($item->fresh()->status)->toBe(ItemStatus::Sold);
});

test('selling a card takes it out of any submission still being prepared', function () {
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->create();
    $preparing = GradingSubmission::factory()->for($user)->create();
    $preparing->collectionItems()->attach($item->id);

    $this->actingAs($user)
        ->post(route('sales.store'), ['collection_item_id' => $item->id, 'platform' => 'local', 'sold_on' => now()->toDateString(), 'sale_price' => '10'])
        ->assertSessionHasNoErrors();

    expect($preparing->collectionItems()->count())->toBe(0);
});

test('a card can’t be sold unless it is on hand', function (Closure $makeItem) {
    $user = User::factory()->create();
    $item = $makeItem($user);

    $this->actingAs($user)
        ->post(route('sales.store'), ['collection_item_id' => $item->id, 'platform' => 'ebay', 'sold_on' => now()->toDateString(), 'sale_price' => '10'])
        ->assertSessionHasErrors('collection_item_id');

    expect(Sale::query()->count())->toBe(0);
})->with([
    'a card already sold' => [fn (User $user) => CollectionItem::factory()->for($user)->status(ItemStatus::Sold)->create()],
    'a card at grading' => [fn (User $user) => CollectionItem::factory()->for($user)->status(ItemStatus::AtGrading)->create()],
]);

test('a card can be marked as traded', function () {
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('trades.store'), ['collection_item_id' => $item->id])
        ->assertSessionHasNoErrors();

    expect($item->fresh()->status)->toBe(ItemStatus::Traded)
        ->and(Sale::query()->count())->toBe(0);
});

test('a teammate can sell a card someone else added', function () {
    $item = CollectionItem::factory()->create();
    $teammate = User::factory()->create();

    $this->actingAs($teammate)
        ->post(route('sales.store'), ['collection_item_id' => $item->id, 'platform' => 'local', 'sold_on' => now()->toDateString(), 'sale_price' => '10'])
        ->assertSessionHasNoErrors();

    expect(Sale::query()->sole()->user_id)->toBe($teammate->id)
        ->and($item->fresh()->status)->toBe(ItemStatus::Sold);
});

test('a card that isn’t on hand can’t be marked as traded', function () {
    $item = CollectionItem::factory()->status(ItemStatus::Sold)->create();

    $this->actingAs(User::factory()->create())
        ->post(route('trades.store'), ['collection_item_id' => $item->id])
        ->assertSessionHasErrors(['collection_item_id' => 'Only cards you have on hand can be traded.']);

    expect($item->fresh()->status)->toBe(ItemStatus::Sold);
});

test('deleting a sale returns the card to owned', function () {
    $user = User::factory()->create();
    $sale = Sale::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('sales.destroy', $sale))
        ->assertSessionHasNoErrors();

    expect(Sale::query()->count())->toBe(0)
        ->and($sale->collectionItem->fresh()->status)->toBe(ItemStatus::Owned);
});

test('a teammate can delete a sale someone else recorded', function () {
    $sale = Sale::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('sales.destroy', $sale))
        ->assertSessionHasNoErrors();

    expect(Sale::query()->count())->toBe(0);
});

test('the ledger totals the filtered year and platform', function () {
    $user = User::factory()->create();
    $cheap = CollectionItem::factory()->for($user)->status(ItemStatus::Sold)->create(['purchase_price' => 2000]);
    $unknownCost = CollectionItem::factory()->for($user)->status(ItemStatus::Sold)->create(['purchase_price' => null]);
    Sale::factory()->for($user)->for($cheap)->create(['platform' => SalePlatform::Ebay, 'sold_on' => '2025-06-01', 'sale_price' => 10000, 'fees' => 1300, 'shipping_cost' => 400]);
    Sale::factory()->for($user)->for($unknownCost)->create(['platform' => SalePlatform::Ebay, 'sold_on' => '2025-09-01', 'sale_price' => 5000, 'fees' => 0, 'shipping_cost' => 0]);
    Sale::factory()->for($user)->create(['platform' => SalePlatform::CardShow, 'sold_on' => '2025-07-01']);
    Sale::factory()->for($user)->create(['platform' => SalePlatform::Ebay, 'sold_on' => '2024-07-01']);
    $teammatesItem = CollectionItem::factory()->status(ItemStatus::Sold)->create(['purchase_price' => 1000]);
    Sale::factory()->for($teammatesItem)->create(['platform' => SalePlatform::Ebay, 'sold_on' => '2025-07-01', 'sale_price' => 3000, 'fees' => 0, 'shipping_cost' => 0]);

    $this->actingAs($user)
        ->get(route('sales.index', ['year' => 2025, 'platform' => 'ebay']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sales/index')
            ->has('sales.data', 3)
            ->where('totals.count', 3)
            ->where('totals.gross', 18000)
            ->where('totals.fees', 1300)
            ->where('totals.shipping', 400)
            ->where('totals.net', 16300)
            ->where('totals.cost', 3000)
            ->where('totals.profit', 13300)
            ->where('totals.missing_cost', 1));
});

test('the CSV export includes every sale for the chosen year', function () {
    $user = User::factory()->create();
    $mine = Sale::factory()->for($user)->create(['sold_on' => '2025-05-05', 'buyer' => '=HYPERLINK("x")']);
    Sale::factory()->for($user)->create(['sold_on' => '2024-05-05', 'buyer' => 'last year buyer']);
    $teammates = Sale::factory()->create(['sold_on' => '2025-06-06']);

    $response = $this->actingAs($user)->get(route('sales.export', ['year' => 2025]));

    $response->assertOk()->assertDownload('sales-2025.csv');

    $rows = array_map(str_getcsv(...), array_filter(explode("\n", $response->streamedContent())));

    expect($rows)->toHaveCount(3)
        ->and($rows[0][0])->toBe('Date sold')
        ->and($rows[1][0])->toBe('2025-05-05')
        ->and($rows[1][1])->toBe($mine->collectionItem->title())
        ->and($rows[1][10])->toBe('\'=HYPERLINK("x")')
        ->and($rows[2][1])->toBe($teammates->collectionItem->title());
});
