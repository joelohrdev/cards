<?php

namespace App\Http\Controllers\Ebay;

use App\Http\Controllers\Controller;
use App\Models\EbayAccount;
use App\Services\Ebay\NotificationSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * eBay Marketplace Account Deletion notifications, required before eBay issues production keys.
 *
 * @see https://developer.ebay.com/marketplace-account-deletion
 */
class AccountDeletionController extends Controller
{
    /**
     * Answer eBay's endpoint validation challenge.
     */
    public function challenge(Request $request): JsonResponse
    {
        $request->validate(['challenge_code' => ['required', 'string', 'max:500']]);

        $token = (string) config('services.ebay.deletion_verification_token');
        abort_if($token === '', 404);

        return response()->json([
            'challengeResponse' => hash('sha256', $request->query('challenge_code').$token.route('ebay.notifications.deletion')),
        ]);
    }

    /**
     * Delete the stored tokens for an eBay user who closed their eBay account.
     */
    public function store(Request $request, NotificationSignature $signature): Response
    {
        abort_unless($signature->isValid($request->header('X-EBAY-SIGNATURE'), $request->getContent()), 412);

        $ebayUserId = $request->json('notification.data.userId');

        if (is_string($ebayUserId) && $ebayUserId !== '') {
            EbayAccount::query()->where('ebay_user_id', $ebayUserId)->delete();
        }

        return response()->noContent(200);
    }
}
