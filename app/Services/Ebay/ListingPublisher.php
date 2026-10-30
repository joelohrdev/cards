<?php

namespace App\Services\Ebay;

use App\Enums\ItemStatus;
use App\Enums\ListingStatus;
use App\Models\EbayAccount;
use App\Models\EbayListing;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Pushes a listing to eBay through the Inventory API and records the outcome on the listing.
 *
 * Publishing is createOrReplaceInventoryItem → createOffer (or updateOffer) → publishOffer.
 *
 * @see https://developer.ebay.com/api-docs/sell/static/inventory/publishing-offers.html
 */
class ListingPublisher
{
    public function __construct(private EbayClient $client) {}

    /**
     * Publish a draft. On failure the listing stays a draft and the eBay error is stored on it.
     */
    public function publish(EbayListing $listing): bool
    {
        try {
            $account = $this->accountFor($listing);
            $this->pushInventoryAndOffer($account, $listing);
            $listingId = $this->client->publishOffer($account, (string) $listing->offer_id);
        } catch (EbayException $exception) {
            $listing->forceFill(['status' => ListingStatus::Draft, 'error' => $exception->getMessage()])->save();

            return false;
        }

        DB::transaction(function () use ($listing, $listingId): void {
            $listing->forceFill([
                'listing_id' => $listingId,
                'status' => ListingStatus::Active,
                'error' => null,
                'published_at' => now(),
                'ended_at' => null,
            ])->save();

            $listing->collectionItem->update(['status' => ItemStatus::Listed]);
        });

        return true;
    }

    /**
     * Push the listing's current title, description and price to its live eBay listing.
     *
     * @throws EbayException
     */
    public function revise(EbayListing $listing): void
    {
        $this->pushInventoryAndOffer($this->accountFor($listing), $listing);

        $listing->forceFill(['error' => null])->save();
    }

    /**
     * End the live listing. The card goes back to being simply owned.
     *
     * @throws EbayException
     */
    public function end(EbayListing $listing): void
    {
        if ($listing->offer_id !== null) {
            $this->client->withdrawOffer($this->accountFor($listing), $listing->offer_id);
        }

        DB::transaction(function () use ($listing): void {
            $listing->forceFill(['status' => ListingStatus::Ended, 'ended_at' => now(), 'error' => null])->save();

            if ($listing->collectionItem->status === ItemStatus::Listed) {
                $listing->collectionItem->update(['status' => ItemStatus::Owned]);
            }
        });
    }

    /**
     * @throws EbayException
     */
    private function pushInventoryAndOffer(EbayAccount $account, EbayListing $listing): void
    {
        if (! $account->isReadyToList()) {
            throw new EbayException('Choose your shipping, payment and return policies and set your item location in eBay settings before publishing.');
        }

        $content = ListingContent::for($listing->collectionItem);

        $this->client->createOrReplaceInventoryItem(
            $account,
            $listing->sku,
            $content->inventoryItem($listing->title, $listing->description),
        );

        $offer = $this->offerPayload($account, $listing);
        $offerId = $listing->offer_id ?? $this->client->findOfferId($account, $listing->sku);

        if ($offerId === null) {
            $offerId = $this->client->createOffer($account, [
                'sku' => $listing->sku,
                'marketplaceId' => $this->client->marketplaceId(),
                'format' => 'FIXED_PRICE',
                ...$offer,
            ]);
        } else {
            $this->client->updateOffer($account, $offerId, $offer);
        }

        $listing->forceFill(['offer_id' => $offerId])->save();
    }

    /**
     * Offer fields shared by createOffer and updateOffer.
     *
     * @return array<string, mixed>
     */
    private function offerPayload(EbayAccount $account, EbayListing $listing): array
    {
        return [
            'availableQuantity' => 1,
            'categoryId' => ListingContent::CATEGORY_ID,
            'listingDescription' => ListingContent::descriptionHtml($listing->description),
            'listingDuration' => 'GTC',
            'listingPolicies' => [
                'fulfillmentPolicyId' => $account->fulfillment_policy_id,
                'paymentPolicyId' => $account->payment_policy_id,
                'returnPolicyId' => $account->return_policy_id,
            ],
            'merchantLocationKey' => $account->merchant_location_key,
            'pricingSummary' => [
                'price' => ['value' => Money::toDollars($listing->price), 'currency' => $this->client->currency()],
            ],
        ];
    }

    /**
     * @throws EbayException
     */
    private function accountFor(EbayListing $listing): EbayAccount
    {
        $account = EbayAccount::query()->where('user_id', $listing->user_id)->first();

        if ($account === null) {
            throw new EbayException('Connect your eBay account in eBay settings before publishing.');
        }

        return $account;
    }
}
