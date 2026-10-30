<?php

use App\Models\EbayAccount;
use App\Models\User;
use App\Services\Ebay\EbayClient;
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
        'services.ebay.auth_url' => 'https://auth.sandbox.ebay.com',
    ]);
});

test('connecting sends the seller to eBay with the selling scopes', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('ebay.connect'));

    $location = $response->headers->get('Location');
    expect($location)->toStartWith('https://auth.sandbox.ebay.com/oauth2/authorize?')
        ->toContain('client_id=client-id')
        ->toContain('redirect_uri=Cards-RuName')
        ->toContain(rawurlencode('https://api.ebay.com/oauth/api_scope/sell.inventory'))
        ->toContain(rawurlencode('https://api.ebay.com/oauth/api_scope/commerce.identity.readonly'))
        ->toContain('state='.session('ebay_oauth_state'));
});

test('the callback exchanges the code and stores encrypted tokens for the user', function () {
    $user = User::factory()->create();
    Http::fake([
        'api.sandbox.ebay.com/identity/v1/oauth2/token' => Http::response([
            'access_token' => 'new-access-token',
            'expires_in' => 7200,
            'refresh_token' => 'new-refresh-token',
            'refresh_token_expires_in' => 47304000,
        ]),
        'apiz.sandbox.ebay.com/commerce/identity/v1/user/' => Http::response(['userId' => 'ebay-user-1', 'username' => 'cardseller']),
    ]);

    $this->actingAs($user)
        ->withSession(['ebay_oauth_state' => 'expected-state'])
        ->get(route('ebay.callback', ['code' => 'auth-code', 'state' => 'expected-state']))
        ->assertRedirect(route('ebay.settings'));

    $account = $user->ebayAccount()->sole();
    expect($account->access_token)->toBe('new-access-token')
        ->and($account->refresh_token)->toBe('new-refresh-token')
        ->and($account->ebay_user_id)->toBe('ebay-user-1')
        ->and($account->username)->toBe('cardseller')
        ->and($account->getRawOriginal('refresh_token'))->not->toContain('new-refresh-token');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.sandbox.ebay.com/identity/v1/oauth2/token'
        && $request['grant_type'] === 'authorization_code'
        && $request['code'] === 'auth-code'
        && $request['redirect_uri'] === 'Cards-RuName'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('client-id:client-secret')));
});

test('the callback rejects a state that does not match the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['ebay_oauth_state' => 'expected-state'])
        ->get(route('ebay.callback', ['code' => 'auth-code', 'state' => 'forged-state']))
        ->assertRedirect(route('ebay.settings'));

    expect($user->ebayAccount()->exists())->toBeFalse();
    Http::assertNothingSent();
});

test('an expired access token is refreshed with the stored refresh token before calling eBay', function () {
    $account = EbayAccount::factory()->create([
        'access_token' => 'stale-token',
        'access_token_expires_at' => now()->subMinute(),
        'refresh_token' => 'stored-refresh-token',
    ]);
    Http::fake([
        'api.sandbox.ebay.com/identity/v1/oauth2/token' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 7200]),
        'api.sandbox.ebay.com/sell/account/v1/fulfillment_policy*' => Http::response(['fulfillmentPolicies' => [['fulfillmentPolicyId' => 'f1', 'name' => 'Standard envelope']]]),
    ]);

    $policies = app(EbayClient::class)->policies($account, 'fulfillment');

    expect($policies)->toBe([['id' => 'f1', 'name' => 'Standard envelope']])
        ->and($account->fresh()->access_token)->toBe('fresh-token')
        ->and($account->fresh()->access_token_expires_at->isFuture())->toBeTrue();
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/oauth2/token')
        && $request['grant_type'] === 'refresh_token'
        && $request['refresh_token'] === 'stored-refresh-token');
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'fulfillment_policy') && $request->hasHeader('Authorization', 'Bearer fresh-token'));
});

test('disconnecting deletes the stored eBay account', function () {
    $account = EbayAccount::factory()->create();

    $this->actingAs($account->user)
        ->delete(route('ebay.disconnect'))
        ->assertRedirect(route('ebay.settings'));

    expect(EbayAccount::query()->exists())->toBeFalse();
});

test('the settings page explains which environment variables to set when eBay is not configured', function () {
    config(['services.ebay.client_id' => null]);

    $this->actingAs(User::factory()->create())
        ->get(route('ebay.settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ebay/settings')
            ->where('configured', false)
            ->where('envVars.1.name', 'EBAY_CLIENT_ID'));
});
