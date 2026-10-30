<?php

use App\Models\EbayAccount;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'services.ebay.client_id' => 'client-id',
        'services.ebay.client_secret' => 'client-secret',
        'services.ebay.ru_name' => 'Cards-RuName',
        'services.ebay.api_url' => 'https://api.sandbox.ebay.com',
        'services.ebay.deletion_verification_token' => 'verification-token-of-at-least-32-characters',
    ]);
});

/**
 * Sign a body the way eBay does and fake the public key lookup.
 */
function signedDeletionHeader(string $body): string
{
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    openssl_sign($body, $signature, $key, OPENSSL_ALGO_SHA1);
    $publicKey = str_replace("\n", '', openssl_pkey_get_details($key)['key']);

    Http::fake([
        'api.sandbox.ebay.com/identity/v1/oauth2/token' => Http::response(['access_token' => 'app-token', 'expires_in' => 7200]),
        'api.sandbox.ebay.com/commerce/notification/v1/public_key/key-1' => Http::response(['algorithm' => 'ECDSA', 'digest' => 'SHA1', 'key' => $publicKey]),
    ]);

    return base64_encode(json_encode(['alg' => 'ECDSA', 'kid' => 'key-1', 'signature' => base64_encode($signature), 'digest' => 'SHA1']));
}

function deletionBody(string $userId): string
{
    return json_encode([
        'metadata' => ['topic' => 'MARKETPLACE_ACCOUNT_DELETION', 'schemaVersion' => '1.0'],
        'notification' => ['notificationId' => 'n-1', 'data' => ['username' => 'cardseller', 'userId' => $userId, 'eiasToken' => 'abc']],
    ]);
}

test('the challenge is answered with the sha256 of code, token and endpoint', function () {
    $endpoint = route('ebay.notifications.deletion');

    $this->getJson(route('ebay.notifications.deletion', ['challenge_code' => 'abc123']))
        ->assertOk()
        ->assertExactJson(['challengeResponse' => hash('sha256', 'abc123verification-token-of-at-least-32-characters'.$endpoint)]);
});

test('a signed deletion notification deletes the matching eBay account without a CSRF token', function () {
    $doomed = EbayAccount::factory()->create(['ebay_user_id' => 'ebay-user-1']);
    $kept = EbayAccount::factory()->create(['ebay_user_id' => 'ebay-user-2']);
    $body = deletionBody('ebay-user-1');

    $this->call('POST', route('ebay.notifications.deletion.store'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_EBAY_SIGNATURE' => signedDeletionHeader($body),
    ], $body)->assertOk();

    expect(EbayAccount::query()->whereKey($doomed->id)->exists())->toBeFalse()
        ->and(EbayAccount::query()->whereKey($kept->id)->exists())->toBeTrue();
});

test('a deletion notification with a bad signature is rejected with 412', function () {
    $account = EbayAccount::factory()->create(['ebay_user_id' => 'ebay-user-1']);
    $header = signedDeletionHeader(deletionBody('someone-else'));

    $this->call('POST', route('ebay.notifications.deletion.store'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_EBAY_SIGNATURE' => $header,
    ], deletionBody('ebay-user-1'))->assertStatus(412);

    expect($account->fresh())->not->toBeNull();
});
