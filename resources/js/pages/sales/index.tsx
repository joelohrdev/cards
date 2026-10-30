import { Head, Link, router } from '@inertiajs/react';
import { Download, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/kit/empty-state';
import { NativeSelect } from '@/components/kit/field';
import { PageHeader } from '@/components/kit/page-header';
import { Pagination } from '@/components/kit/pagination';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { money, shortDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import collection from '@/routes/collection';
import sales from '@/routes/sales';
import type { Option, Paginated } from '@/types';

type SaleRow = {
    id: number;
    sold_on: string;
    platform: string;
    platform_label: string;
    sale_price: number;
    fees: number;
    shipping_cost: number;
    net: number;
    cost: number | null;
    profit: number | null;
    buyer: string | null;
    item: {
        id: number;
        title: string;
        image: string | null;
        card: { number: string; name: string };
    };
};

type Filters = { year?: number; platform?: string };

type Props = {
    sales: Paginated<SaleRow>;
    filters: Filters;
    years: number[];
    platforms: Option[];
    totals: {
        count: number;
        gross: number;
        fees: number;
        shipping: number;
        net: number;
        cost: number;
        profit: number;
        missing_cost: number;
    };
};

function signed(cents: number): string {
    return `${cents >= 0 ? '+' : '−'}${money(Math.abs(cents))}`;
}

export default function SalesIndex({
    sales: page,
    filters,
    years,
    platforms,
    totals,
}: Props) {
    const [deleting, setDeleting] = useState<SaleRow | null>(null);
    const query = { year: filters.year, platform: filters.platform };

    function filter(next: Filters) {
        router.get(sales.index.url(), { ...query, ...next } as never, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    }

    return (
        <>
            <Head title="Sales" />

            <PageHeader
                title="Sales"
                description="Every card you’ve sold, with fees, shipping, and profit over what you paid."
                actions={
                    <>
                        <NativeSelect
                            aria-label="Year"
                            className="w-32"
                            value={filters.year ?? ''}
                            onChange={(event) =>
                                filter({
                                    year: event.target.value
                                        ? Number(event.target.value)
                                        : undefined,
                                })
                            }
                        >
                            <option value="">All years</option>
                            {years.map((year) => (
                                <option key={year} value={year}>
                                    {year}
                                </option>
                            ))}
                        </NativeSelect>
                        <NativeSelect
                            aria-label="Platform"
                            className="w-44"
                            value={filters.platform ?? ''}
                            onChange={(event) =>
                                filter({
                                    platform: event.target.value || undefined,
                                })
                            }
                        >
                            <option value="">All platforms</option>
                            {platforms.map((platform) => (
                                <option
                                    key={platform.value}
                                    value={platform.value}
                                >
                                    {platform.label}
                                </option>
                            ))}
                        </NativeSelect>
                        <Button asChild variant="outline">
                            <a href={sales.export.url({ query })} download>
                                <Download /> Export CSV
                            </a>
                        </Button>
                    </>
                }
            />

            <div className="mb-8 grid grid-cols-2 gap-px overflow-hidden rounded-lg border bg-border sm:grid-cols-3 lg:grid-cols-6">
                {(
                    [
                        [
                            'Gross sales',
                            money(totals.gross),
                            `${totals.count.toLocaleString()} ${totals.count === 1 ? 'sale' : 'sales'}`,
                        ],
                        ['Fees', money(totals.fees), ''],
                        ['Shipping', money(totals.shipping), ''],
                        [
                            'Net proceeds',
                            money(totals.net),
                            'After fees and shipping',
                        ],
                        [
                            'Cost basis',
                            money(totals.cost),
                            totals.missing_cost > 0
                                ? `${totals.missing_cost} with no purchase price`
                                : 'What you paid',
                        ],
                        [
                            'Profit',
                            signed(totals.profit),
                            'Net minus cost basis',
                        ],
                    ] as const
                ).map(([label, value, detail]) => (
                    <div key={label} className="bg-card p-4">
                        <p className="text-sm text-muted-foreground">{label}</p>
                        <p
                            className={cn(
                                'font-display text-2xl font-bold tabular',
                                label === 'Profit' &&
                                    totals.count > 0 &&
                                    (totals.profit >= 0
                                        ? 'text-gain'
                                        : 'text-loss'),
                            )}
                        >
                            {value}
                        </p>
                        {detail && (
                            <p className="truncate text-xs text-muted-foreground">
                                {detail}
                            </p>
                        )}
                    </div>
                ))}
            </div>

            {page.data.length === 0 ? (
                <EmptyState
                    title={
                        filters.year || filters.platform
                            ? 'No sales match these filters'
                            : 'No sales yet'
                    }
                    description={
                        filters.year || filters.platform
                            ? 'Try another year or platform.'
                            : 'Open a card in your collection and choose “Record sale” when it sells. It will show up here with your profit.'
                    }
                    action={
                        <Button asChild variant="outline">
                            <Link href={collection.index()}>
                                Go to your collection
                            </Link>
                        </Button>
                    }
                />
            ) : (
                <div className="overflow-x-auto rounded-lg border bg-card">
                    <table className="w-full text-sm">
                        <thead className="text-left text-xs text-muted-foreground">
                            <tr className="border-b">
                                <th className="px-4 py-2 font-medium">Sold</th>
                                <th className="px-4 py-2 font-medium">Card</th>
                                <th className="px-4 py-2 font-medium">Where</th>
                                <th className="px-4 py-2 text-right font-medium">
                                    Price
                                </th>
                                <th className="px-4 py-2 text-right font-medium">
                                    Fees + shipping
                                </th>
                                <th className="px-4 py-2 text-right font-medium">
                                    Net
                                </th>
                                <th className="px-4 py-2 text-right font-medium">
                                    Paid
                                </th>
                                <th className="px-4 py-2 text-right font-medium">
                                    Profit
                                </th>
                                <th className="w-10" />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {page.data.map((sale) => (
                                <tr key={sale.id} className="hover:bg-muted/40">
                                    <td className="px-4 py-2 whitespace-nowrap text-muted-foreground">
                                        {shortDate(sale.sold_on)}
                                    </td>
                                    <td className="max-w-md px-4 py-2">
                                        <Link
                                            href={collection.show(sale.item.id)}
                                            className="line-clamp-2 hover:text-primary hover:underline"
                                        >
                                            {sale.item.title}
                                        </Link>
                                        {sale.buyer && (
                                            <span className="text-xs text-muted-foreground">
                                                to {sale.buyer}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-2 whitespace-nowrap">
                                        {sale.platform_label}
                                    </td>
                                    <td className="px-4 py-2 text-right tabular">
                                        {money(sale.sale_price)}
                                    </td>
                                    <td className="px-4 py-2 text-right text-muted-foreground tabular">
                                        {money(sale.fees + sale.shipping_cost)}
                                    </td>
                                    <td className="px-4 py-2 text-right font-medium tabular">
                                        {money(sale.net)}
                                    </td>
                                    <td className="px-4 py-2 text-right text-muted-foreground tabular">
                                        {money(sale.cost)}
                                    </td>
                                    <td
                                        className={cn(
                                            'px-4 py-2 text-right font-medium tabular',
                                            sale.profit !== null &&
                                                (sale.profit >= 0
                                                    ? 'text-gain'
                                                    : 'text-loss'),
                                        )}
                                    >
                                        {sale.profit === null
                                            ? '—'
                                            : signed(sale.profit)}
                                    </td>
                                    <td className="px-2 py-2 text-right">
                                        <button
                                            type="button"
                                            className="text-muted-foreground hover:text-destructive"
                                            aria-label={`Delete the sale of ${sale.item.title}`}
                                            onClick={() => setDeleting(sale)}
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <Pagination page={page} />

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete this sale?</DialogTitle>
                        <DialogDescription>
                            {deleting?.item.title} goes back to “Owned” in your
                            collection. Use this if the sale fell through or was
                            entered by mistake.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleting(null)}
                        >
                            Keep it
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                deleting &&
                                router.delete(sales.destroy.url(deleting.id), {
                                    preserveScroll: true,
                                    onSuccess: () => setDeleting(null),
                                })
                            }
                        >
                            Delete sale
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
