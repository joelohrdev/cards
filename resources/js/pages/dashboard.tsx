import { Head, Link } from '@inertiajs/react';
import { Plus, Rows3 } from 'lucide-react';
import { CardTile } from '@/components/cards/card-tile';
import { BarList } from '@/components/kit/bar-list';
import { EmptyState } from '@/components/kit/empty-state';
import { PageHeader } from '@/components/kit/page-header';
import { Panel } from '@/components/kit/panel';
import { Stat } from '@/components/kit/stat';
import { ValueChart } from '@/components/kit/value-chart';
import { Button } from '@/components/ui/button';
import { compactMoney, count, money, percent } from '@/lib/format';
import collection from '@/routes/collection';
import sets from '@/routes/sets';
import type { CollectionItem } from '@/types';

type Props = {
    totals: {
        count: number;
        value: number;
        cost: number;
        unrealized: number;
        unvalued: number;
        graded: number;
    };
    sales: { count: number; net: number; profit: number; year: number };
    bySport: { sport: string; value: string; count: number; total: number }[];
    byDecade: { decade: number; count: number }[];
    history: { date: string; value: number; cost: number }[];
    topItems: CollectionItem[];
    recentItems: CollectionItem[];
    setProgress: { id: number; name: string; owned: number; total: number }[];
    activity: { grading: number; listings: number; wants: number };
};

export default function Dashboard({
    totals,
    sales,
    bySport,
    byDecade,
    history,
    topItems,
    recentItems,
    setProgress,
    activity,
}: Props) {
    if (totals.count === 0 && recentItems.length === 0) {
        return (
            <>
                <Head title="Overview" />
                <PageHeader title="Your collection" />
                <EmptyState
                    title="Start your collection"
                    description="Add cards one at a time, type in a stack from one set with Quick add, or tick off what you own from a set checklist."
                    action={
                        <div className="flex flex-wrap justify-center gap-2">
                            <Button asChild>
                                <Link href={collection.create()}>
                                    <Plus /> Add a card
                                </Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={collection.quickAdd()}>
                                    <Rows3 /> Quick add a stack
                                </Link>
                            </Button>
                        </div>
                    }
                />
            </>
        );
    }

    const gain = totals.unrealized;

    return (
        <>
            <Head title="Overview" />

            <section className="mb-8 grid gap-6 rounded-xl bg-rail p-6 text-rail-foreground sm:p-8 lg:grid-cols-[1.2fr_2fr]">
                <div>
                    <p className="text-rail-muted">
                        Estimated collection value
                    </p>
                    <p className="font-display text-6xl leading-none font-bold tabular sm:text-7xl">
                        {compactMoney(totals.value)}
                    </p>
                    <p className="mt-3 text-sm text-rail-muted">
                        {count(totals.count, 'card')} in hand ·{' '}
                        <span
                            className={
                                gain >= 0 ? 'text-[#4cc38a]' : 'text-[#f47067]'
                            }
                        >
                            {gain >= 0 ? '+' : '−'}
                            {money(Math.abs(gain))}
                        </span>{' '}
                        vs. cost on valued cards
                    </p>
                </div>
                <dl className="grid grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-4 lg:self-end">
                    {[
                        ['Cost basis', money(totals.cost)],
                        ['Graded', totals.graded.toLocaleString()],
                        [`Sold in ${sales.year}`, money(sales.net)],
                        [
                            'Profit on sales',
                            `${sales.profit >= 0 ? '' : '−'}${money(Math.abs(sales.profit))}`,
                        ],
                    ].map(([label, value]) => (
                        <div key={label}>
                            <dt className="text-sm text-rail-muted">{label}</dt>
                            <dd className="font-display text-2xl font-semibold tabular">
                                {value}
                            </dd>
                        </div>
                    ))}
                </dl>
            </section>

            <div className="grid gap-6 xl:grid-cols-3">
                <Panel title="Value over time" className="xl:col-span-2">
                    <ValueChart points={history} />
                </Panel>

                <Panel title="Needs attention">
                    <ul className="divide-y text-sm">
                        <AttentionRow
                            label="Cards without a value"
                            value={totals.unvalued}
                            href={collection.index.url({
                                query: { has_value: 'no' },
                            })}
                        />
                        <AttentionRow
                            label="Cards without photos"
                            href={collection.index.url({
                                query: { has_image: 'no' },
                            })}
                        />
                        <AttentionRow
                            label="Unplaced cards"
                            href={collection.index.url({
                                query: { location: 'none' },
                            })}
                        />
                        <AttentionRow
                            label="At grading"
                            value={activity.grading}
                            href={collection.index.url({
                                query: { status: ['at-grading'] },
                            })}
                        />
                        <AttentionRow
                            label="Listed for sale"
                            value={activity.listings}
                            href={collection.index.url({
                                query: { status: ['listed'] },
                            })}
                        />
                    </ul>
                </Panel>
            </div>

            {topItems.length > 0 && (
                <section className="mt-8">
                    <div className="mb-3 flex items-end justify-between">
                        <h2 className="font-display text-2xl font-bold">
                            Most valuable
                        </h2>
                        <Link
                            href={collection.index({
                                query: { sort: 'value_desc' },
                            })}
                            className="text-sm font-medium text-primary hover:underline"
                        >
                            See all by value
                        </Link>
                    </div>
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                        {topItems.map((item) => (
                            <CardTile key={item.id} item={item} />
                        ))}
                    </div>
                </section>
            )}

            <div className="mt-8 grid gap-6 lg:grid-cols-3">
                <Panel title="By sport">
                    {bySport.length > 0 ? (
                        <BarList
                            rows={bySport.map((row) => ({
                                key: row.value,
                                label: row.sport,
                                value: row.count,
                                display: row.count.toLocaleString(),
                                detail: compactMoney(row.total),
                                href: collection.index({
                                    query: { sport: row.value },
                                }),
                            }))}
                        />
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            No cards yet.
                        </p>
                    )}
                </Panel>

                <Panel title="By decade">
                    <BarList
                        rows={byDecade.map((row) => ({
                            key: String(row.decade),
                            label: `${row.decade}s`,
                            value: row.count,
                            display: row.count.toLocaleString(),
                            href: collection.index({
                                query: {
                                    year_from: row.decade,
                                    year_to: row.decade + 9,
                                },
                            }),
                        }))}
                    />
                </Panel>

                <Panel title="Set progress">
                    {setProgress.length > 0 ? (
                        <ul className="space-y-3">
                            {setProgress.map((set) => (
                                <li key={set.id}>
                                    <Link
                                        href={sets.show(set.id)}
                                        className="block hover:opacity-80"
                                    >
                                        <div className="flex items-baseline justify-between gap-3 text-sm">
                                            <span className="truncate font-medium">
                                                {set.name}
                                            </span>
                                            <span className="shrink-0 text-muted-foreground tabular">
                                                {set.owned}/{set.total}
                                            </span>
                                        </div>
                                        <div className="mt-1 h-2 rounded-full bg-muted">
                                            <div
                                                className="h-2 rounded-full bg-primary"
                                                style={{
                                                    width: `${Math.max(2, percent(set.owned, set.total))}%`,
                                                }}
                                            />
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Add a checklist to a set to track how close you are
                            to completing it.
                        </p>
                    )}
                </Panel>
            </div>

            <section className="mt-8">
                <div className="mb-3 flex items-end justify-between">
                    <h2 className="font-display text-2xl font-bold">
                        Recently added
                    </h2>
                    <Link
                        href={collection.index()}
                        className="text-sm font-medium text-primary hover:underline"
                    >
                        Browse collection
                    </Link>
                </div>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                    {recentItems.map((item) => (
                        <CardTile key={item.id} item={item} />
                    ))}
                </div>
            </section>

            <div className="mt-8 grid gap-6 sm:grid-cols-3">
                <Panel>
                    <Stat
                        label="Want list"
                        value={activity.wants.toLocaleString()}
                        detail="cards you're hunting"
                    />
                </Panel>
                <Panel>
                    <Stat
                        label="Sales this year"
                        value={sales.count.toLocaleString()}
                    />
                </Panel>
                <Panel>
                    <Stat
                        label="Unrealized gain"
                        value={`${gain >= 0 ? '+' : '−'}${money(Math.abs(gain))}`}
                        tone={gain >= 0 ? 'gain' : 'loss'}
                    />
                </Panel>
            </div>
        </>
    );
}

function AttentionRow({
    label,
    value,
    href,
}: {
    label: string;
    value?: number;
    href: string;
}) {
    return (
        <li>
            <Link
                href={href}
                className="flex items-center justify-between py-2.5 hover:text-primary"
            >
                <span>{label}</span>
                {value !== undefined ? (
                    <span className="font-display text-lg font-semibold tabular">
                        {value.toLocaleString()}
                    </span>
                ) : (
                    <span className="text-xs text-muted-foreground">
                        Review
                    </span>
                )}
            </Link>
        </li>
    );
}
