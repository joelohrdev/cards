<?php

namespace App\Services\Ebay;

use App\Enums\ItemStatus;
use App\Enums\ListingStatus;
use App\Enums\SalePlatform;
use App\Models\EbayAccount;
use App\Models\EbayListing;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Turns paid eBay orders into sales, matching line items to listings by SKU.
 *
 * Safe to run repeatedly: an item that already has a sale is never sold twice.
 *
 * @see https://developer.ebay.com/api-docs/sell/fulfillment/resources/order/methods/getOrders
 */
class OrderSync
{
    /**
     * How far back to look the first time an account is synced.
     */
    private const int INITIAL_LOOKBACK_DAYS = 30;

    /**
     * Payment states that mean the buyer has paid.
     *
     * @var list<string>
     */
    private const array PAID_STATES = ['PAID', 'PARTIALLY_REFUNDED'];

    public function __construct(private EbayClient $client) {}

    /**
     * Sync one account and return the number of sales recorded.
     *
     * @throws EbayException
     */
    public function sync(EbayAccount $account): int
    {
        $startedAt = now();
        $since = $account->orders_synced_at?->copy()->subHours(2) ?? now()->subDays(self::INITIAL_LOOKBACK_DAYS);
        $recorded = 0;

        foreach ($this->client->orders($account, $since) as $order) {
            if (! $this->isPaid($order)) {
                continue;
            }

            foreach ($this->saleLines($order) as $line) {
                $recorded += $this->recordSale($account, $order, $line) ? 1 : 0;
            }
        }

        $account->forceFill(['orders_synced_at' => $startedAt])->save();

        return $recorded;
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function isPaid(array $order): bool
    {
        return in_array($order['orderPaymentStatus'] ?? null, self::PAID_STATES, true)
            && data_get($order, 'cancelStatus.cancelState') !== 'CANCELED';
    }

    /**
     * Line items with their share of the order's marketplace fee, all amounts in cents.
     *
     * @param  array<string, mixed>  $order
     * @return list<array{sku: string, price: int, fees: int, shipping: int}>
     */
    private function saleLines(array $order): array
    {
        $lineItems = $order['lineItems'] ?? [];

        $lines = collect(is_array($lineItems) ? $lineItems : [])
            ->filter(fn (array $line): bool => filled($line['sku'] ?? null))
            ->map(fn (array $line): array => [
                'sku' => (string) $line['sku'],
                'price' => (int) Money::toCents(data_get($line, 'lineItemCost.value', 0)),
                'shipping' => (int) Money::toCents(data_get($line, 'deliveryCost.shippingCost.value', 0)),
            ])
            ->values();

        $orderFees = (int) Money::toCents(data_get($order, 'totalMarketplaceFee.value', 0));
        $orderSubtotal = max(1, $lines->sum('price'));

        return array_values($lines
            ->map(fn (array $line): array => [...$line, 'fees' => (int) round($orderFees * $line['price'] / $orderSubtotal)])
            ->all());
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  array{sku: string, price: int, fees: int, shipping: int}  $line
     */
    private function recordSale(EbayAccount $account, array $order, array $line): bool
    {
        $listing = EbayListing::query()
            ->where('user_id', $account->user_id)
            ->where('sku', $line['sku'])
            ->with('collectionItem.sale')
            ->first();

        if ($listing === null) {
            return false;
        }

        return DB::transaction(function () use ($account, $order, $line, $listing): bool {
            $item = $listing->collectionItem;
            $created = false;

            if ($item->sale === null) {
                $account->user->sales()->create([
                    'collection_item_id' => $item->id,
                    'platform' => SalePlatform::Ebay,
                    'sold_on' => Carbon::parse($order['creationDate'] ?? now())->toDateString(),
                    'sale_price' => $line['price'],
                    'fees' => $line['fees'],
                    'shipping_cost' => 0,
                    'buyer' => data_get($order, 'buyer.username'),
                    'external_order_id' => (string) $order['orderId'],
                    'notes' => $line['shipping'] > 0
                        ? 'Buyer paid $'.Money::toDollars($line['shipping']).' for shipping. Add your postage cost once the card ships.'
                        : null,
                ]);

                $item->update(['status' => ItemStatus::Sold]);
                $created = true;
            }

            if ($listing->status !== ListingStatus::Sold) {
                $listing->forceFill(['status' => ListingStatus::Sold, 'ended_at' => $listing->ended_at ?? now(), 'error' => null])->save();
            }

            return $created;
        });
    }
}
