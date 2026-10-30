import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/kit/empty-state';
import { NativeSelect } from '@/components/kit/field';
import { PageHeader } from '@/components/kit/page-header';
import { Pagination } from '@/components/kit/pagination';
import { Button } from '@/components/ui/button';
import { percent } from '@/lib/format';
import { cn } from '@/lib/utils';
import sets from '@/routes/sets';
import type { CardSetSummary, Option, Paginated } from '@/types';

type SetRow = CardSetSummary & { cards_count: number; owned_count: number };

export default function SetsIndex({
    sets: page,
    query,
    sport,
    mine,
    sports,
}: {
    sets: Paginated<SetRow>;
    query: string;
    sport: string | null;
    mine: boolean;
    sports: Option[];
}) {
    const [search, setSearch] = useState(query);

    function visit(params: Record<string, unknown>) {
        router.get(
            sets.index.url(),
            {
                q: search || undefined,
                sport: sport ?? undefined,
                mine: mine ? 1 : undefined,
                ...params,
            } as never,
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Sets & checklists" />
            <PageHeader
                title="Sets & checklists"
                description="The catalog is shared by everyone using this app. Paste a set’s checklist once, then tick off the cards you own."
                actions={
                    <Button asChild>
                        <Link href={sets.create()}>
                            <Plus /> New set
                        </Link>
                    </Button>
                }
            />

            <div className="mb-5 flex flex-wrap items-center gap-2">
                <form
                    className="relative w-full max-w-sm"
                    onSubmit={(event) => {
                        event.preventDefault();
                        visit({ q: search || undefined });
                    }}
                >
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="1987 topps, prizm, upper deck…"
                        className="h-10 w-full rounded-md border border-input bg-card pr-3 pl-9 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/30"
                        aria-label="Search sets"
                    />
                </form>
                <NativeSelect
                    value={sport ?? ''}
                    onChange={(event) =>
                        visit({ sport: event.target.value || undefined })
                    }
                    className="h-10 w-auto"
                    aria-label="Sport"
                >
                    <option value="">All sports</option>
                    {sports.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </NativeSelect>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={mine}
                        onChange={(event) =>
                            visit({
                                mine: event.target.checked ? 1 : undefined,
                            })
                        }
                        className="size-4 accent-[var(--primary)]"
                    />
                    Only sets I own cards from
                </label>
            </div>

            {page.data.length === 0 ? (
                <EmptyState
                    title={query ? 'No sets match' : 'No sets yet'}
                    description="Create a set and paste its checklist, or add a card and the set is created for you."
                    action={
                        <Button asChild>
                            <Link href={sets.create()}>New set</Link>
                        </Button>
                    }
                />
            ) : (
                <div className="overflow-hidden rounded-lg border bg-card">
                    <ul className="divide-y">
                        {page.data.map((set) => {
                            const total = Math.max(
                                set.card_count ?? 0,
                                set.cards_count,
                            );
                            const done = percent(set.owned_count, total);

                            return (
                                <li key={set.id}>
                                    <Link
                                        href={sets.show(set.id)}
                                        className="grid grid-cols-[4rem_1fr] items-center gap-4 px-4 py-3 hover:bg-muted/40 sm:grid-cols-[4rem_1fr_14rem]"
                                    >
                                        <span className="font-display text-2xl font-bold text-muted-foreground tabular">
                                            {set.season ?? set.year}
                                        </span>
                                        <span className="min-w-0">
                                            <span className="block truncate font-medium">
                                                {set.manufacturer} {set.name}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {set.sport_label} ·{' '}
                                                {set.cards_count
                                                    ? `${set.cards_count.toLocaleString()} in checklist`
                                                    : 'No checklist yet'}
                                            </span>
                                        </span>
                                        <span className="col-span-2 sm:col-span-1">
                                            {set.owned_count > 0 ? (
                                                <>
                                                    <span className="flex justify-between text-xs text-muted-foreground tabular">
                                                        <span>
                                                            {set.owned_count}/
                                                            {total} owned
                                                        </span>
                                                        <span>{done}%</span>
                                                    </span>
                                                    <span className="mt-1 block h-1.5 rounded-full bg-muted">
                                                        <span
                                                            className={cn(
                                                                'block h-1.5 rounded-full bg-primary',
                                                            )}
                                                            style={{
                                                                width: `${Math.max(2, done)}%`,
                                                            }}
                                                        />
                                                    </span>
                                                </>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">
                                                    None owned
                                                </span>
                                            )}
                                        </span>
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            )}

            <Pagination page={page} />
        </>
    );
}
