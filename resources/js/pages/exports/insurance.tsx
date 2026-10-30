import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { EmptyState } from '@/components/kit/empty-state';
import { NativeSelect } from '@/components/kit/field';
import { PageHeader } from '@/components/kit/page-header';
import { Stat } from '@/components/kit/stat';
import { Button } from '@/components/ui/button';
import { count, money, shortDate } from '@/lib/format';
import exportsRoutes from '@/routes/exports';
import type { Option } from '@/types';

type InventoryItem = {
    id: number;
    title: string;
    image: string | null;
    card: {
        number: string;
        name: string;
        set: {
            full_name: string;
            year: number;
            name: string;
            manufacturer: string | null;
        };
    };
    parallel: { id: number; name: string; print_run: number | null } | null;
    condition_label: string | null;
    cert_number: string | null;
    status_label: string;
    location: string | null;
    storage_position: string | null;
    purchase_price: number | null;
    purchase_date: string | null;
    estimated_value: number | null;
};

type Props = {
    status: string;
    statuses: Option[];
    generatedAt: string;
    owner: string;
    totals: { count: number; value: number; cost: number; unvalued: number };
    items: InventoryItem[];
};

export default function InsuranceReport({
    status,
    statuses,
    generatedAt,
    owner,
    totals,
    items,
}: Props) {
    return (
        <>
            <Head title="Insurance inventory" />

            <div className="no-print mb-4">
                <Link
                    href={exportsRoutes.index()}
                    className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-3.5" /> Export
                </Link>
            </div>

            <PageHeader
                title="Collection inventory"
                description={
                    <>
                        Prepared for {owner} on {shortDate(generatedAt)}. Values
                        are the owner’s estimates.
                    </>
                }
                actions={
                    <>
                        <NativeSelect
                            value={status}
                            onChange={(event) =>
                                router.get(
                                    exportsRoutes.insurance.url(),
                                    {
                                        status:
                                            event.target.value === 'in-hand'
                                                ? undefined
                                                : event.target.value,
                                    },
                                    { preserveScroll: true },
                                )
                            }
                            className="w-auto"
                            aria-label="Which cards to include"
                        >
                            {statuses.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </NativeSelect>
                        <Button onClick={() => window.print()}>
                            <Printer /> Print or save as PDF
                        </Button>
                    </>
                }
            />

            <section className="mb-6 grid grid-cols-2 gap-6 rounded-lg border bg-card p-4 sm:grid-cols-4 print:border-black/30">
                <Stat label="Cards" value={totals.count.toLocaleString()} />
                <Stat label="Estimated value" value={money(totals.value)} />
                <Stat label="Total paid" value={money(totals.cost)} />
                <Stat
                    label="Without a value"
                    value={totals.unvalued.toLocaleString()}
                    detail={
                        totals.unvalued > 0
                            ? 'Not counted in the total'
                            : undefined
                    }
                />
            </section>

            {items.length === 0 ? (
                <EmptyState
                    title="No cards to list"
                    description="Nothing matches this status. Choose “In hand” or “Every status” above."
                />
            ) : (
                <div className="overflow-x-auto rounded-lg border bg-card print:overflow-visible print:border-0">
                    <table className="w-full text-sm print:text-[10pt]">
                        <thead className="border-b bg-muted/50 text-left text-xs text-muted-foreground print:bg-transparent">
                            <tr>
                                <th className="w-10 px-3 py-2 text-right font-medium">
                                    #
                                </th>
                                <th className="px-3 py-2 font-medium">Card</th>
                                <th className="px-3 py-2 font-medium">
                                    Condition
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Location
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Paid
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Value
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {items.map((item, index) => (
                                <tr
                                    key={item.id}
                                    className="break-inside-avoid"
                                >
                                    <td className="px-3 py-2 text-right align-top text-muted-foreground tabular">
                                        {index + 1}
                                    </td>
                                    <td className="px-3 py-2 align-top">
                                        <div className="flex gap-3">
                                            <div className="aspect-[5/7] w-10 shrink-0 overflow-hidden rounded-[3px] border bg-muted">
                                                {item.image ? (
                                                    <img
                                                        src={item.image}
                                                        alt=""
                                                        loading="lazy"
                                                        className="h-full w-full object-cover"
                                                    />
                                                ) : (
                                                    <span className="flex h-full items-center justify-center font-display text-[0.65rem] font-bold text-muted-foreground">
                                                        {item.card.number.slice(
                                                            0,
                                                            4,
                                                        )}
                                                    </span>
                                                )}
                                            </div>
                                            <div className="min-w-0">
                                                <p className="font-medium">
                                                    {item.title}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {item.status_label}
                                                    {item.purchase_date &&
                                                        ` · bought ${shortDate(item.purchase_date)}`}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-3 py-2 align-top whitespace-nowrap">
                                        {item.condition_label ?? '—'}
                                        {item.cert_number && (
                                            <div className="text-xs text-muted-foreground tabular">
                                                Cert {item.cert_number}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 align-top text-muted-foreground">
                                        {item.location ?? '—'}
                                        {item.storage_position &&
                                            ` · ${item.storage_position}`}
                                    </td>
                                    <td className="px-3 py-2 text-right align-top text-muted-foreground tabular">
                                        {money(item.purchase_price)}
                                    </td>
                                    <td className="px-3 py-2 text-right align-top font-medium tabular">
                                        {money(item.estimated_value)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot className="border-t-2">
                            <tr className="font-semibold">
                                <td className="px-3 py-2" />
                                <td className="px-3 py-2" colSpan={3}>
                                    Total for {count(totals.count, 'card')}
                                </td>
                                <td className="px-3 py-2 text-right tabular">
                                    {money(totals.cost)}
                                </td>
                                <td className="px-3 py-2 text-right tabular">
                                    {money(totals.value)}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            )}
        </>
    );
}
