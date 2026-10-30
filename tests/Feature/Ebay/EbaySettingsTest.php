<?php

use App\Models\EbayAccount;
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
    ]);
});

test('the settings page lists the seller’s business policies from eBay', function () {
    $account = EbayAccount::factory()->create();
    Http::fake([
        '*/fulfillment_policy*' => Http::response(['fulfillmentPolicies' => [['fulfillmentPolicyId' => 'f1', 'name' => 'PWE']]]),
        '*/payment_policy*' => Http::response(['paymentPolicies' => [['paymentPolicyId' => 'p1', 'name' => 'Managed payments']]]),
        '*/return_policy*' => Http::response(['returnPolicies' => [['returnPolicyId' => 'r1', 'name' => 'No returns']]]),
    ]);

    $this->actingAs($account->user)
        ->get(route('ebay.settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ebay/settings')
            ->where('configured', true)
            ->where('policies.fulfillment.0', ['id' => 'f1', 'name' => 'PWE'])
            ->where('policies.return.0.id', 'r1')
            ->where('account.ready', false));
});

test('the chosen business policies are saved on the account', function () {
    $account = EbayAccount::factory()->create();

    $this->actingAs($account->user)
        ->put(route('ebay.settings.policies'), [
            'fulfillment_policy_id' => 'f1',
            'payment_policy_id' => 'p1',
            'return_policy_id' => 'r1',
        ])
        ->assertRedirect(route('ebay.settings'));

    expect($account->fresh()->only(['fulfillment_policy_id', 'payment_policy_id', 'return_policy_id']))
        ->toBe(['fulfillment_policy_id' => 'f1', 'payment_policy_id' => 'p1', 'return_policy_id' => 'r1']);
});

test('saving the item location creates the inventory location on eBay', function () {
    $account = EbayAccount::factory()->create();
    Http::fake(['api.sandbox.ebay.com/sell/inventory/v1/location/cards-default' => Http::response(null, 204)]);

    $this->actingAs($account->user)
        ->put(route('ebay.settings.location'), ['postal_code' => '98101', 'country' => 'us'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('ebay.settings'));

    expect($account->fresh()->merchant_location_key)->toBe('cards-default');
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request['location']['address'] === ['postalCode' => '98101', 'country' => 'US']
        && $request['locationTypes'] === ['WAREHOUSE']);
});

test('an existing item location is updated instead of failing', function () {
    $account = EbayAccount::factory()->create();
    Http::fake([
        'api.sandbox.ebay.com/sell/inventory/v1/location/cards-default' => Http::response(['errors' => [['message' => 'Location already exists']]], 409),
        'api.sandbox.ebay.com/sell/inventory/v1/location/cards-default/update_location_details' => Http::response(null, 204),
    ]);

    $this->actingAs($account->user)
        ->put(route('ebay.settings.location'), ['postal_code' => '98101', 'country' => 'US'])
        ->assertSessionHasNoErrors();

    expect($account->fresh()->merchant_location_key)->toBe('cards-default');
});

test('saving policies requires a connected eBay account', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('ebay.settings.policies'), [
            'fulfillment_policy_id' => 'f1',
            'payment_policy_id' => 'p1',
            'return_policy_id' => 'r1',
        ])
        ->assertSessionHasErrors('account');
});
