<?php

namespace App\Http\Controllers\Selling;

use App\Actions\Selling\RecordSale;
use App\Enums\ItemStatus;
use App\Enums\SalePlatform;
use App\Http\Controllers\Controller;
use App\Http\Requests\Selling\SaleRequest;
use App\Models\CollectionItem;
use App\Models\Sale;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The sales ledger: what sold, for how much, and the profit after fees and cost basis.
 */
class SaleController extends Controller
{
    /**
     * Relations needed to show a sold card's title and thumbnail.
     *
     * @var list<string>
     */
    private const array ITEM_RELATIONS = [
        'collectionItem.card.cardSet.manufacturer',
        'collectionItem.parallel',
        'collectionItem.frontImage',
    ];

    /**
     * List sales with totals, filtered by year and platform.
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $totals = $this->filteredSales($filters)
            ->toBase()
            ->leftJoin('collection_items', 'collection_items.id', '=', 'sales.collection_item_id')
            ->selectRaw('count(*) as sale_count')
            ->selectRaw('coalesce(sum(sales.sale_price), 0) as gross')
            ->selectRaw('coalesce(sum(sales.fees), 0) as fees')
            ->selectRaw('coalesce(sum(sales.shipping_cost), 0) as shipping')
            ->selectRaw('coalesce(sum(collection_items.purchase_price), 0) as cost')
            ->selectRaw('sum(case when collection_items.purchase_price is null then 1 else 0 end) as missing_cost')
            ->first();

        $gross = (int) ($totals->gross ?? 0);
        $fees = (int) ($totals->fees ?? 0);
        $shipping = (int) ($totals->shipping ?? 0);
        $cost = (int) ($totals->cost ?? 0);
        $net = $gross - $fees - $shipping;

        $sales = $this->filteredSales($filters)
            ->with(self::ITEM_RELATIONS)
            ->orderByDesc('sold_on')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Sale $sale): array => $this->row($sale));

        $years = Sale::query()->pluck('sold_on')
            ->map(fn ($date): int => (int) $date->year)
            ->push((int) now()->year)
            ->unique()
            ->sortDesc()
            ->values();

        return Inertia::render('sales/index', [
            'sales' => $sales,
            'filters' => (object) array_filter($filters),
            'years' => $years,
            'platforms' => SalePlatform::options(),
            'totals' => [
                'count' => (int) ($totals->sale_count ?? 0),
                'gross' => $gross,
                'fees' => $fees,
                'shipping' => $shipping,
                'net' => $net,
                'cost' => $cost,
                'profit' => $net - $cost,
                'missing_cost' => (int) ($totals->missing_cost ?? 0),
            ],
        ]);
    }

    /**
     * Record a sale and mark the card sold.
     */
    public function store(SaleRequest $request, RecordSale $recordSale): RedirectResponse
    {
        $validated = $request->validated();
        $item = CollectionItem::query()->findOrFail($request->integer('collection_item_id'));

        $sale = $recordSale->handle($request->user(), $item, [
            'platform' => $request->string('platform')->toString(),
            'sold_on' => $request->string('sold_on')->toString(),
            'buyer' => $validated['buyer'] ?? null,
            'external_order_id' => $validated['external_order_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'sale_price' => (int) Money::toCents($validated['sale_price']),
            'fees' => Money::toCents($validated['fees'] ?? null) ?? 0,
            'shipping_cost' => Money::toCents($validated['shipping_cost'] ?? null) ?? 0,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Recorded the sale of {$item->title()} for $".Money::toDollars($sale->sale_price).'.',
        ]);

        return back();
    }

    /**
     * Delete a sale. The card goes back to "Owned".
     */
    public function destroy(Sale $sale): RedirectResponse
    {
        DB::transaction(function () use ($sale): void {
            $sale->collectionItem()->update(['status' => ItemStatus::Owned->value]);
            $sale->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sale deleted. The card is back in your collection.']);

        return back();
    }

    /**
     * Download the filtered sales as CSV for tax records.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $sales = $this->filteredSales($filters)
            ->with(self::ITEM_RELATIONS)
            ->orderBy('sold_on')
            ->orderBy('id');

        $filename = 'sales'.($filters['year'] ? '-'.$filters['year'] : '').($filters['platform'] ? '-'.$filters['platform'] : '').'.csv';

        return response()->streamDownload(function () use ($sales): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['Date sold', 'Card', 'Platform', 'Sale price', 'Fees', 'Shipping', 'Net proceeds', 'Cost basis', 'Date acquired', 'Profit', 'Buyer', 'Order ID', 'Notes'], escape: '');

            foreach ($sales->lazy(500) as $sale) {
                /** @var Sale $sale */
                $item = $sale->collectionItem;
                $cost = $item->purchase_price;
                $net = $sale->netProceeds();

                fputcsv($out, [
                    $sale->sold_on->toDateString(),
                    self::csvText($item->title()),
                    $sale->platform->label(),
                    Money::toDollars($sale->sale_price),
                    Money::toDollars($sale->fees),
                    Money::toDollars($sale->shipping_cost),
                    Money::toDollars($net),
                    Money::toDollars($cost),
                    $item->purchase_date?->toDateString(),
                    $cost === null ? null : Money::toDollars($net - $cost),
                    self::csvText($sale->buyer),
                    self::csvText($sale->external_order_id),
                    self::csvText($sale->notes),
                ], escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{year: int|null, platform: string|null}
     */
    private function filters(Request $request): array
    {
        $year = $request->integer('year');
        $platform = SalePlatform::tryFrom((string) $request->string('platform'));

        return [
            'year' => $year >= 1900 && $year <= 2200 ? $year : null,
            'platform' => $platform?->value,
        ];
    }

    /**
     * @param  array{year: int|null, platform: string|null}  $filters
     * @return Builder<Sale>
     */
    private function filteredSales(array $filters): Builder
    {
        return Sale::query()
            ->when($filters['year'], fn (Builder $query, int $year) => $query->whereYear('sales.sold_on', $year))
            ->when($filters['platform'], fn (Builder $query, string $platform) => $query->where('sales.platform', $platform));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Sale $sale): array
    {
        /** @var CollectionItem $item */
        $item = $sale->collectionItem;
        $net = $sale->netProceeds();

        return [
            'id' => $sale->id,
            'sold_on' => $sale->sold_on->toDateString(),
            'platform' => $sale->platform->value,
            'platform_label' => $sale->platform->label(),
            'sale_price' => $sale->sale_price,
            'fees' => $sale->fees,
            'shipping_cost' => $sale->shipping_cost,
            'net' => $net,
            'cost' => $item->purchase_price,
            'profit' => $item->purchase_price === null ? null : $net - $item->purchase_price,
            'buyer' => $sale->buyer,
            'item' => [
                'id' => $item->id,
                'title' => $item->title(),
                'image' => $item->frontImage?->url,
                'card' => ['number' => $item->card->number, 'name' => $item->card->name],
            ],
        ];
    }

    /**
     * Neutralize spreadsheet formulas in user-entered text (CSV injection).
     */
    private static function csvText(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
