<?php

namespace App\Http\Controllers\Ebay;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ebay\EbayLocationRequest;
use App\Http\Requests\Ebay\EbayPoliciesRequest;
use App\Models\EbayAccount;
use App\Services\Ebay\EbayClient;
use App\Services\Ebay\EbayException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EbaySettingsController extends Controller
{
    /**
     * The inventory location key every listing ships from.
     */
    public const string LOCATION_KEY = 'cards-default';

    /**
     * Environment variables the eBay integration reads.
     *
     * @var list<array{name: string, description: string}>
     */
    private const array ENV_VARS = [
        ['name' => 'EBAY_ENVIRONMENT', 'description' => '“sandbox” while testing, “production” for real listings.'],
        ['name' => 'EBAY_CLIENT_ID', 'description' => 'App ID (Client ID) from your eBay developer keyset.'],
        ['name' => 'EBAY_CLIENT_SECRET', 'description' => 'Cert ID (Client Secret) from the same keyset.'],
        ['name' => 'EBAY_RU_NAME', 'description' => 'The RuName (eBay Redirect URL name) for your user tokens.'],
        ['name' => 'EBAY_MARKETPLACE_ID', 'description' => 'Where you sell, e.g. EBAY_US.'],
        ['name' => 'EBAY_DELETION_VERIFICATION_TOKEN', 'description' => '32–80 characters you choose; paste the same value into the account deletion notification settings.'],
    ];

    /**
     * Show the connection status, business policies and item location.
     */
    public function show(Request $request, EbayClient $client): Response
    {
        $account = $request->user()->ebayAccount;
        $policies = null;
        $policiesError = null;

        if ($account !== null && EbayClient::isConfigured()) {
            try {
                $policies = [
                    'fulfillment' => $client->policies($account, 'fulfillment'),
                    'payment' => $client->policies($account, 'payment'),
                    'return' => $client->policies($account, 'return'),
                ];
            } catch (EbayException $exception) {
                $policiesError = $exception->getMessage();
            }
        }

        return Inertia::render('ebay/settings', [
            'configured' => EbayClient::isConfigured(),
            'environment' => (string) config('services.ebay.environment', 'sandbox'),
            'marketplace' => $client->marketplaceId(),
            'envVars' => self::ENV_VARS,
            'callbackUrl' => route('ebay.callback'),
            'deletionEndpointUrl' => route('ebay.notifications.deletion'),
            'account' => $account ? [
                'username' => $account->username,
                'connected_at' => $account->created_at?->toIso8601String(),
                'fulfillment_policy_id' => $account->fulfillment_policy_id,
                'payment_policy_id' => $account->payment_policy_id,
                'return_policy_id' => $account->return_policy_id,
                'merchant_location_key' => $account->merchant_location_key,
                'ready' => $account->isReadyToList(),
                'orders_synced_at' => $account->orders_synced_at?->toIso8601String(),
            ] : null,
            'policies' => $policies,
            'policiesError' => $policiesError,
            'sellerHubUrl' => rtrim((string) config('services.ebay.web_url'), '/').'/sh/ovw',
        ]);
    }

    /**
     * Save the shipping, payment and return policies used for every listing.
     */
    public function updatePolicies(EbayPoliciesRequest $request): RedirectResponse
    {
        $this->accountFor($request)->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Listing policies saved.']);

        return to_route('ebay.settings');
    }

    /**
     * Create or update the inventory location listings ship from.
     */
    public function updateLocation(EbayLocationRequest $request, EbayClient $client): RedirectResponse
    {
        $account = $this->accountFor($request);

        try {
            $client->saveInventoryLocation($account, self::LOCATION_KEY, 'Card collection', [
                'postal_code' => $request->string('postal_code')->toString(),
                'country' => $request->string('country')->toString(),
                'city' => $request->filled('city') ? $request->string('city')->toString() : null,
                'state' => $request->filled('state') ? $request->string('state')->toString() : null,
            ]);
        } catch (EbayException $exception) {
            throw ValidationException::withMessages(['postal_code' => 'eBay didn’t accept this location: '.$exception->getMessage()]);
        }

        $account->update(['merchant_location_key' => self::LOCATION_KEY]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Item location saved.']);

        return to_route('ebay.settings');
    }

    private function accountFor(Request $request): EbayAccount
    {
        $account = $request->user()->ebayAccount;

        if ($account === null) {
            throw ValidationException::withMessages(['account' => 'Connect your eBay account first.']);
        }

        return $account;
    }
}
