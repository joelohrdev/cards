<?php

namespace App\Http\Controllers\Ebay;

use App\Http\Controllers\Controller;
use App\Services\Ebay\EbayClient;
use App\Services\Ebay\EbayException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EbayConnectionController extends Controller
{
    private const string STATE_SESSION_KEY = 'ebay_oauth_state';

    /**
     * Send the seller to eBay to grant access.
     */
    public function redirect(Request $request, EbayClient $client): Response
    {
        if (! EbayClient::isConfigured()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => EbayException::notConfigured()->getMessage()]);

            return to_route('ebay.settings');
        }

        $state = Str::random(40);
        $request->session()->put(self::STATE_SESSION_KEY, $state);

        return Inertia::location($client->consentUrl($state));
    }

    /**
     * Handle eBay's redirect back after the seller accepts (or declines) access.
     */
    public function callback(Request $request, EbayClient $client): RedirectResponse
    {
        $expectedState = $request->session()->pull(self::STATE_SESSION_KEY);

        if (! $request->filled('code')) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'eBay access wasn’t granted. Choose “Connect eBay” to try again.']);

            return to_route('ebay.settings');
        }

        if (! is_string($expectedState) || ! hash_equals($expectedState, (string) $request->query('state'))) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'That eBay sign-in link expired. Choose “Connect eBay” to start again.']);

            return to_route('ebay.settings');
        }

        try {
            $tokens = $client->exchangeCode((string) $request->query('code'));
            $ebayUser = $client->user($tokens['access_token']);
        } catch (EbayException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'eBay connection failed: '.$exception->getMessage()]);

            return to_route('ebay.settings');
        }

        $request->user()->ebayAccount()->updateOrCreate([], [
            ...$tokens,
            'ebay_user_id' => $ebayUser['userId'],
            'username' => $ebayUser['username'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Connected to eBay'.($ebayUser['username'] ? " as {$ebayUser['username']}." : '.')]);

        return to_route('ebay.settings');
    }

    /**
     * Forget the stored eBay tokens. Listings already on eBay stay live.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->ebayAccount()->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Disconnected eBay. Listings already on eBay stay live.']);

        return to_route('ebay.settings');
    }
}
