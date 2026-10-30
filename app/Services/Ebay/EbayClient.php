<?php

namespace App\Services\Ebay;

use App\Models\EbayAccount;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin client for the eBay Sell APIs (OAuth, Account, Inventory, Fulfillment).
 *
 * Every call that acts for a seller goes through {@see accessTokenFor()}, which refreshes the
 * user access token with the stored refresh token when it is about to expire.
 *
 * @see https://developer.ebay.com/api-docs/static/oauth-authorization-code-grant.html
 */
class EbayClient
{
    /**
     * OAuth scopes the app asks the seller to grant.
     *
     * @var list<string>
     */
    public const array SCOPES = [
        'https://api.ebay.com/oauth/api_scope',
        'https://api.ebay.com/oauth/api_scope/sell.inventory',
        'https://api.ebay.com/oauth/api_scope/sell.account',
        'https://api.ebay.com/oauth/api_scope/sell.fulfillment',
        'https://api.ebay.com/oauth/api_scope/commerce.identity.readonly',
    ];

    /**
     * Currency and content language for each supported marketplace.
     *
     * @var array<string, array{currency: string, language: string}>
     */
    public const array MARKETPLACES = [
        'EBAY_US' => ['currency' => 'USD', 'language' => 'en-US'],
        'EBAY_CA' => ['currency' => 'CAD', 'language' => 'en-CA'],
        'EBAY_GB' => ['currency' => 'GBP', 'language' => 'en-GB'],
        'EBAY_AU' => ['currency' => 'AUD', 'language' => 'en-AU'],
        'EBAY_DE' => ['currency' => 'EUR', 'language' => 'de-DE'],
    ];

    /**
     * Refresh the access token this many seconds before eBay says it expires.
     */
    private const int EXPIRY_MARGIN_SECONDS = 120;

    /**
     * Whether the app has the credentials it needs to talk to eBay.
     */
    public static function isConfigured(): bool
    {
        return filled(config('services.ebay.client_id'))
            && filled(config('services.ebay.client_secret'))
            && filled(config('services.ebay.ru_name'));
    }

    public function marketplaceId(): string
    {
        return (string) (config('services.ebay.marketplace_id') ?: 'EBAY_US');
    }

    public function currency(): string
    {
        return self::MARKETPLACES[$this->marketplaceId()]['currency'] ?? 'USD';
    }

    public function contentLanguage(): string
    {
        return self::MARKETPLACES[$this->marketplaceId()]['language'] ?? 'en-US';
    }

    /**
     * The eBay sign-in page that asks the seller to grant this app access.
     */
    public function consentUrl(string $state): string
    {
        $this->ensureConfigured();

        return rtrim((string) config('services.ebay.auth_url'), '/').'/oauth2/authorize?'.http_build_query([
            'client_id' => config('services.ebay.client_id'),
            'redirect_uri' => config('services.ebay.ru_name'),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchange an authorization code for user tokens.
     *
     * @return array{access_token: string, access_token_expires_at: CarbonInterface, refresh_token: string, refresh_token_expires_at: CarbonInterface|null}
     */
    public function exchangeCode(string $code): array
    {
        $response = $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('services.ebay.ru_name'),
        ]);

        return [
            'access_token' => (string) $response->json('access_token'),
            'access_token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 7200)),
            'refresh_token' => (string) $response->json('refresh_token'),
            'refresh_token_expires_at' => $response->json('refresh_token_expires_in') !== null
                ? now()->addSeconds((int) $response->json('refresh_token_expires_in'))
                : null,
        ];
    }

    /**
     * Mint a new access token from the account's refresh token and store it.
     */
    public function refresh(EbayAccount $account): EbayAccount
    {
        try {
            $response = $this->tokenRequest([
                'grant_type' => 'refresh_token',
                'refresh_token' => $account->refresh_token,
                'scope' => implode(' ', self::SCOPES),
            ]);
        } catch (EbayException $exception) {
            if ($exception->getCode() === 400 || $exception->getCode() === 401) {
                throw EbayException::reconnectRequired();
            }

            throw $exception;
        }

        $account->forceFill([
            'access_token' => (string) $response->json('access_token'),
            'access_token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 7200)),
        ])->save();

        return $account;
    }

    /**
     * A valid access token for the account, refreshing it first when it is about to expire.
     */
    public function accessTokenFor(EbayAccount $account): string
    {
        if ($account->access_token_expires_at->lte(now()->addSeconds(self::EXPIRY_MARGIN_SECONDS))) {
            $this->refresh($account);
        }

        return $account->access_token;
    }

    /**
     * The signed-in seller's eBay user ID and username.
     *
     * @return array{userId: string|null, username: string|null}
     */
    public function user(EbayAccount|string $accountOrToken): array
    {
        $token = $accountOrToken instanceof EbayAccount ? $this->accessTokenFor($accountOrToken) : $accountOrToken;

        $response = $this->send(fn (): Response => $this->baseRequest($this->identityUrl(), retry: true)
            ->withToken($token)
            ->get('/commerce/identity/v1/user/'));

        return [
            'userId' => $response->json('userId'),
            'username' => $response->json('username'),
        ];
    }

    /**
     * Business policies of one kind: "fulfillment", "payment" or "return".
     *
     * @return list<array{id: string, name: string}>
     */
    public function policies(EbayAccount $account, string $kind): array
    {
        $response = $this->send(fn (): Response => $this->sellerRequest($account, retry: true)
            ->get("/sell/account/v1/{$kind}_policy", ['marketplace_id' => $this->marketplaceId()]));

        $idKey = "{$kind}PolicyId";

        $policies = $response->json("{$kind}Policies");

        return array_values(collect(is_array($policies) ? $policies : [])
            ->map(fn (array $policy): array => ['id' => (string) $policy[$idKey], 'name' => (string) ($policy['name'] ?? $policy[$idKey])])
            ->all());
    }

    /**
     * Create (or update the address of) a warehouse inventory location.
     *
     * @param  array{postal_code: string, country: string, city?: string|null, state?: string|null}  $address
     */
    public function saveInventoryLocation(EbayAccount $account, string $key, string $name, array $address): void
    {
        $location = array_filter([
            'postalCode' => $address['postal_code'],
            'country' => strtoupper($address['country']),
            'city' => $address['city'] ?? null,
            'stateOrProvince' => $address['state'] ?? null,
        ]);

        $response = $this->attempt(fn (): Response => $this->sellerRequest($account)->post('/sell/inventory/v1/location/'.rawurlencode($key), [
            'location' => ['address' => $location],
            'locationTypes' => ['WAREHOUSE'],
            'merchantLocationStatus' => 'ENABLED',
            'name' => $name,
        ]));

        if ($response->status() === 409) {
            $response = $this->attempt(fn (): Response => $this->sellerRequest($account)
                ->post('/sell/inventory/v1/location/'.rawurlencode($key).'/update_location_details', [
                    'location' => ['address' => $location],
                    'name' => $name,
                ]));
        }

        $this->ensureSuccessful($response);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createOrReplaceInventoryItem(EbayAccount $account, string $sku, array $payload): void
    {
        $this->send(fn (): Response => $this->sellerRequest($account, retry: true)
            ->withHeaders(['Content-Language' => $this->contentLanguage()])
            ->put('/sell/inventory/v1/inventory_item/'.rawurlencode($sku), $payload));
    }

    /**
     * The ID of an existing offer for this SKU on the configured marketplace, if there is one.
     */
    public function findOfferId(EbayAccount $account, string $sku): ?string
    {
        $response = $this->attempt(fn (): Response => $this->sellerRequest($account, retry: true)
            ->get('/sell/inventory/v1/offer', ['sku' => $sku, 'marketplace_id' => $this->marketplaceId()]));

        if ($response->notFound()) {
            return null;
        }

        $this->ensureSuccessful($response);

        $offerId = $response->json('offers.0.offerId');

        return $offerId === null ? null : (string) $offerId;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createOffer(EbayAccount $account, array $payload): string
    {
        $response = $this->send(fn (): Response => $this->sellerRequest($account)
            ->withHeaders(['Content-Language' => $this->contentLanguage()])
            ->post('/sell/inventory/v1/offer', $payload));

        return (string) $response->json('offerId');
    }

    /**
     * Replace an offer. When the offer is published this revises the live listing.
     *
     * @param  array<string, mixed>  $payload
     */
    public function updateOffer(EbayAccount $account, string $offerId, array $payload): void
    {
        $this->send(fn (): Response => $this->sellerRequest($account, retry: true)
            ->withHeaders(['Content-Language' => $this->contentLanguage()])
            ->put('/sell/inventory/v1/offer/'.rawurlencode($offerId), $payload));
    }

    /**
     * Publish an offer as a live listing and return the eBay listing ID.
     */
    public function publishOffer(EbayAccount $account, string $offerId): string
    {
        $response = $this->send(fn (): Response => $this->sellerRequest($account)
            ->post('/sell/inventory/v1/offer/'.rawurlencode($offerId).'/publish'));

        return (string) $response->json('listingId');
    }

    /**
     * End the live listing for an offer. The offer itself stays and can be published again.
     */
    public function withdrawOffer(EbayAccount $account, string $offerId): void
    {
        $this->send(fn (): Response => $this->sellerRequest($account)
            ->post('/sell/inventory/v1/offer/'.rawurlencode($offerId).'/withdraw'));
    }

    /**
     * Orders modified since the given time, following pagination.
     *
     * @return list<array<string, mixed>>
     */
    public function orders(EbayAccount $account, CarbonInterface $modifiedSince, int $maxOrders = 1000): array
    {
        $orders = [];
        $offset = 0;
        $limit = 100;

        do {
            $response = $this->send(fn (): Response => $this->sellerRequest($account, retry: true)
                ->get('/sell/fulfillment/v1/order', [
                    'filter' => 'lastmodifieddate:['.$modifiedSince->copy()->utc()->format('Y-m-d\TH:i:s.v\Z').'..]',
                    'limit' => $limit,
                    'offset' => $offset,
                ]));

            $page = $response->json('orders', []);
            array_push($orders, ...$page);
            $offset += $limit;
        } while ($response->json('next') !== null && $page !== [] && count($orders) < $maxOrders);

        return $orders;
    }

    /**
     * The PEM public key eBay signs a notification with, cached for an hour.
     *
     * @see https://developer.ebay.com/api-docs/commerce/notification/resources/public_key/methods/getPublicKey
     */
    public function notificationPublicKey(string $keyId): string
    {
        return Cache::remember('ebay:notification-key:'.sha1($keyId), now()->addHour(), function () use ($keyId): string {
            $response = $this->send(fn (): Response => $this->baseRequest((string) config('services.ebay.api_url'), retry: true)
                ->withToken($this->applicationToken())
                ->get('/commerce/notification/v1/public_key/'.rawurlencode($keyId)));

            return (string) $response->json('key');
        });
    }

    /**
     * An application (client credentials) access token, cached until shortly before it expires.
     */
    public function applicationToken(): string
    {
        $cached = Cache::get('ebay:application-token');

        if (is_string($cached)) {
            return $cached;
        }

        $response = $this->tokenRequest([
            'grant_type' => 'client_credentials',
            'scope' => 'https://api.ebay.com/oauth/api_scope',
        ]);

        $token = (string) $response->json('access_token');
        Cache::put('ebay:application-token', $token, now()->addSeconds(max(60, (int) $response->json('expires_in', 7200) - self::EXPIRY_MARGIN_SECONDS)));

        return $token;
    }

    /**
     * @param  array<string, string|null>  $form
     */
    private function tokenRequest(array $form): Response
    {
        $this->ensureConfigured();

        return $this->send(fn (): Response => $this->baseRequest((string) config('services.ebay.api_url'))
            ->withBasicAuth((string) config('services.ebay.client_id'), (string) config('services.ebay.client_secret'))
            ->asForm()
            ->post('/identity/v1/oauth2/token', $form));
    }

    private function sellerRequest(EbayAccount $account, bool $retry = false): PendingRequest
    {
        return $this->baseRequest((string) config('services.ebay.api_url'), $retry)
            ->withToken($this->accessTokenFor($account))
            ->withHeaders(['X-EBAY-C-MARKETPLACE-ID' => $this->marketplaceId()]);
    }

    /**
     * Shared settings. Retries are only enabled for idempotent calls (GET and PUT).
     */
    private function baseRequest(string $baseUrl, bool $retry = false): PendingRequest
    {
        $request = Http::baseUrl(rtrim($baseUrl, '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(30);

        if ($retry) {
            $request->retry([200, 1000], 0, fn (Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException
                    && ($exception->response->serverError() || $exception->response->status() === 429)), throw: false);
        }

        return $request;
    }

    /**
     * Run a request and turn any HTTP or connection failure into an {@see EbayException}.
     *
     * @param  callable(): Response  $request
     */
    private function send(callable $request): Response
    {
        return $this->ensureSuccessful($this->attempt($request));
    }

    /**
     * Run a request, returning error responses as-is but turning connection failures into an {@see EbayException}.
     *
     * @param  callable(): Response  $request
     */
    private function attempt(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException $exception) {
            throw new EbayException('Couldn’t reach eBay. Check your connection and try again.', 0, $exception);
        } catch (RequestException $exception) {
            return $exception->response;
        }
    }

    private function ensureSuccessful(Response $response): Response
    {
        if ($response->failed()) {
            throw EbayException::fromResponse($response);
        }

        return $response;
    }

    /**
     * The Identity API lives on the "apiz" host rather than "api".
     */
    private function identityUrl(): string
    {
        return str_replace('://api.', '://apiz.', (string) config('services.ebay.api_url'));
    }

    private function ensureConfigured(): void
    {
        if (! self::isConfigured()) {
            throw EbayException::notConfigured();
        }
    }
}
