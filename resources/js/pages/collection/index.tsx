import { Head, Link, router } from '@inertiajs/react';
import {
    CheckSquare,
    LayoutGrid,
    List,
    Search,
    SlidersHorizontal,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { CardFlags } from '@/components/cards/card-flags';
import { CardThumb } from '@/components/cards/card-thumb';
import { CardTile } from '@/components/cards/card-tile';
import { GradeBadge } from '@/components/cards/grade-badge';
import { TagChip } from '@/components/cards/tag-chip';
import { BulkBar } from '@/components/collection/bulk-bar';
import { FilterPanel } from '@/components/collection/filter-panel';
import type { CollectionFilters } from '@/components/collection/filter-panel';
import { EmptyState } from '@/components/kit/empty-state';
import { NativeSelect } from '@/components/kit/field';
import { Pagination } from '@/components/kit/pagination';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import { compactMoney, money } from '@/lib/format';
import { cn } from '@/lib/utils';
import collection from '@/routes/collection';
import type { CollectionItem, FormOptions, Option, Paginated } from '@/types';

type Parsed = {
    years: number[];
    seasons: string[];
    numbers: string[];
    grades: { grader: string | null; grade: string | null }[];
    print_runs: number[];
    flags: string[];
    graded: boolean | null;
    terms: string[];
};

type Props = {
    items: Paginated<CollectionItem>;
    query: string;
    filters: CollectionFilters;
    parsed: Parsed;
    totals: { count: number; value: number; cost: number };
    sorts: Option[];
    sets: Option[];
    options: FormOptions;
};

const flagLabels: Record<string, string> = {
    is_rookie: 'Rookie',
    is_autograph: 'Autograph',
    is_memorabilia: 'Relic',
    is_short_print: 'Short print',
};

function readView(): 'grid' | 'table' {
    try {
        return window.localStorage.getItem('collection-view') === 'table'
            ? 'table'
            : 'grid';
    } catch {
        return 'grid';
    }
}

export default function CollectionIndex({
    items,
    query,
    filters,
    parsed,
    totals,
    sorts,
    sets,
    options,
}: Props) {
    const [search, setSearch] = useState(query);
    const [view, setView] = useState<'grid' | 'table'>('grid');
    const [selecting, setSelecting] = useState(false);
    const [selected, setSelected] = useState<number[]>([]);
    const [lastClicked, setLastClicked] = useState<number | null>(null);
    const [filtersOpen, setFiltersOpen] = useState(false);

    useEffect(() => setView(readView()), []);
    useEffect(() => setSearch(query), [query]);

    function changeView(next: 'grid' | 'table') {
        setView(next);

        try {
            window.localStorage.setItem('collection-view', next);
        } catch {
            // Storage can be unavailable (private mode); the view just won't persist.
        }
    }

    function visit(nextQuery: string, nextFilters: CollectionFilters) {
        router.get(
            collection.index.url(),
            { q: nextQuery || undefined, ...nextFilters } as never,
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function toggle(item: CollectionItem, event: React.MouseEvent) {
        const ids = items.data.map((entry) => entry.id);

        if (event.shiftKey && lastClicked !== null) {
            const [start, end] = [
                ids.indexOf(lastClicked),
                ids.indexOf(item.id),
            ].sort((a, b) => a - b);
            const range = ids.slice(start, end + 1);
            setSelected((current) =>
                Array.from(new Set([...current, ...range])),
            );
        } else {
            setSelected((current) =>
                current.includes(item.id)
                    ? current.filter((id) => id !== item.id)
                    : [...current, item.id],
            );
        }

        setLastClicked(item.id);
    }

    const activeFilterCount = Object.entries(filters).filter(
        ([key]) => key !== 'sort',
    ).length;

    const chips: string[] = [
        ...parsed.years.map(String),
        ...parsed.seasons,
        ...parsed.numbers.map((number) => `#${number}`),
        ...parsed.grades.map((grade) =>
            [grade.grader, grade.grade].filter(Boolean).join(' '),
        ),
        ...parsed.print_runs.map((run) => `/${run}`),
        ...parsed.flags.map((flag) => flagLabels[flag] ?? flag),
        ...(parsed.graded === null ? [] : [parsed.graded ? 'Graded' : 'Raw']),
        ...parsed.terms.map((term) => `“${term}”`),
    ];

    const filterPanel = (
        <FilterPanel
            filters={filters}
            options={options}
            sets={sets}
            onChange={(next) => visit(search, next)}
        />
    );

    return (
        <>
            <Head title="Collection" />

            <div className="mb-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 className="font-display text-4xl font-bold">
                        Collection
                    </h1>
                    <p className="mt-1 text-muted-foreground tabular">
                        {totals.count.toLocaleString()}{' '}
                        {totals.count === 1 ? 'card' : 'cards'} ·{' '}
                        {compactMoney(totals.value)} estimated ·{' '}
                        {compactMoney(totals.cost)} paid
                    </p>
                </div>

                <form
                    className="flex w-full max-w-xl gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        visit(search.trim(), filters);
                    }}
                >
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search: griffey 1989 upper deck rc psa 9"
                            className="h-11 w-full rounded-lg border border-input bg-card pr-3 pl-9 shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30"
                            aria-label="Search the collection"
                        />
                    </div>
                    <Button type="submit" className="h-11">
                        Search
                    </Button>
                </form>
            </div>

            <div className="grid gap-6 lg:grid-cols-[15rem_1fr]">
                <aside className="hidden lg:block">
                    <div className="sticky top-24 max-h-[calc(100svh-7rem)] overflow-y-auto pr-1">
                        {filterPanel}
                    </div>
                </aside>

                <Sheet open={filtersOpen} onOpenChange={setFiltersOpen}>
                    <SheetContent
                        side="left"
                        className="w-80 overflow-y-auto p-5"
                    >
                        <SheetTitle className="mb-2 font-display text-xl">
                            Filters
                        </SheetTitle>
                        {filterPanel}
                    </SheetContent>
                </Sheet>

                <div className="min-w-0">
                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            className="lg:hidden"
                            onClick={() => setFiltersOpen(true)}
                        >
                            <SlidersHorizontal /> Filters
                            {activeFilterCount > 0 && (
                                <span className="tabular">
                                    ({activeFilterCount})
                                </span>
                            )}
                        </Button>

                        {chips.length > 0 && (
                            <div className="flex flex-wrap items-center gap-1.5 text-sm">
                                <span className="text-muted-foreground">
                                    Matching
                                </span>
                                {chips.map((chip) => (
                                    <span
                                        key={chip}
                                        className="rounded-md bg-card px-2 py-0.5 font-medium ring-1 ring-border"
                                    >
                                        {chip}
                                    </span>
                                ))}
                            </div>
                        )}

                        {(query || activeFilterCount > 0) && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => visit('', {})}
                            >
                                <X /> Clear all
                            </Button>
                        )}

                        <div className="ml-auto flex items-center gap-2">
                            <NativeSelect
                                value={filters.sort ?? 'recent'}
                                onChange={(event) =>
                                    visit(search, {
                                        ...filters,
                                        sort:
                                            event.target.value === 'recent'
                                                ? undefined
                                                : event.target.value,
                                    })
                                }
                                className="h-8 w-auto"
                                aria-label="Sort"
                            >
                                {sorts.map((sort) => (
                                    <option key={sort.value} value={sort.value}>
                                        {sort.label}
                                    </option>
                                ))}
                            </NativeSelect>

                            <div
                                className="flex rounded-md border bg-card p-0.5"
                                role="group"
                                aria-label="View"
                            >
                                <button
                                    type="button"
                                    onClick={() => changeView('grid')}
                                    className={cn(
                                        'rounded p-1.5',
                                        view === 'grid' && 'bg-accent',
                                    )}
                                    aria-label="Grid view"
                                    aria-pressed={view === 'grid'}
                                >
                                    <LayoutGrid className="size-4" />
                                </button>
                                <button
                                    type="button"
                                    onClick={() => changeView('table')}
                                    className={cn(
                                        'rounded p-1.5',
                                        view === 'table' && 'bg-accent',
                                    )}
                                    aria-label="Table view"
                                    aria-pressed={view === 'table'}
                                >
                                    <List className="size-4" />
                                </button>
                            </div>

                            <Button
                                variant={selecting ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => {
                                    setSelecting(!selecting);
                                    setSelected([]);
                                }}
                            >
                                <CheckSquare /> {selecting ? 'Done' : 'Select'}
                            </Button>
                        </div>
                    </div>

                    {selecting && items.data.length > 0 && (
                        <div className="mb-3 flex items-center gap-3 text-sm text-muted-foreground">
                            <button
                                type="button"
                                className="font-medium text-primary hover:underline"
                                onClick={() =>
                                    setSelected(
                                        Array.from(
                                            new Set([
                                                ...selected,
                                                ...items.data.map(
                                                    (item) => item.id,
                                                ),
                                            ]),
                                        ),
                                    )
                                }
                            >
                                Select all {items.data.length} on this page
                            </button>
                            <span>Shift-click to select a range.</span>
                        </div>
                    )}

                    {items.data.length === 0 ? (
                        <EmptyState
                            title={
                                query || activeFilterCount > 0
                                    ? 'No cards match'
                                    : 'No cards yet'
                            }
                            description={
                                query || activeFilterCount > 0
                                    ? 'Try fewer words, or clear a filter. Search matches players, sets, brands, teams, parallels, tags and notes.'
                                    : 'Add your first card, or tick off a set checklist to add many at once.'
                            }
                            action={
                                <Button asChild>
                                    <Link href={collection.create()}>
                                        Add a card
                                    </Link>
                                </Button>
                            }
                        />
                    ) : view === 'grid' ? (
                        <div className="grid grid-cols-2 gap-1 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6">
                            {items.data.map((item) => (
                                <CardTile
                                    key={item.id}
                                    item={item}
                                    selecting={selecting}
                                    selected={selected.includes(item.id)}
                                    onToggle={toggle}
                                />
                            ))}
                        </div>
                    ) : (
                        <CollectionTable
                            items={items.data}
                            selecting={selecting}
                            selected={selected}
                            onToggle={toggle}
                        />
                    )}

                    <Pagination page={items} />
                </div>
            </div>

            {selected.length > 0 && (
                <BulkBar
                    ids={selected}
                    options={options}
                    onClear={() => setSelected([])}
                />
            )}
        </>
    );
}

function CollectionTable({
    items,
    selecting,
    selected,
    onToggle,
}: {
    items: CollectionItem[];
    selecting: boolean;
    selected: number[];
    onToggle: (item: CollectionItem, event: React.MouseEvent) => void;
}) {
    return (
        <div className="overflow-x-auto rounded-lg border bg-card">
            <table className="w-full text-sm">
                <thead className="border-b bg-muted/50 text-left text-xs text-muted-foreground">
                    <tr>
                        {selecting && <th className="w-10 px-3 py-2" />}
                        <th className="px-3 py-2 font-medium">Card</th>
                        <th className="px-3 py-2 font-medium">Set</th>
                        <th className="px-3 py-2 font-medium">Condition</th>
                        <th className="hidden px-3 py-2 font-medium md:table-cell">
                            Storage
                        </th>
                        <th className="hidden px-3 py-2 font-medium xl:table-cell">
                            Tags
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
                    {items.map((item) => {
                        const isSelected = selected.includes(item.id);

                        return (
                            <tr
                                key={item.id}
                                className={cn(
                                    'hover:bg-muted/40',
                                    isSelected && 'bg-primary/10',
                                )}
                                onClick={
                                    selecting
                                        ? (event) => onToggle(item, event)
                                        : undefined
                                }
                            >
                                {selecting && (
                                    <td className="px-3 py-2">
                                        <input
                                            type="checkbox"
                                            readOnly
                                            checked={isSelected}
                                            className="size-4 accent-[var(--primary)]"
                                            aria-label={`Select ${item.card.name}`}
                                        />
                                    </td>
                                )}
                                <td className="px-3 py-2">
                                    <div className="flex items-center gap-3">
                                        <CardThumb
                                            item={item}
                                            className="w-8"
                                        />
                                        <div className="min-w-0">
                                            {selecting ? (
                                                <span className="font-medium">
                                                    {item.card.name}
                                                </span>
                                            ) : (
                                                <Link
                                                    href={collection.show(
                                                        item.id,
                                                    )}
                                                    className="font-medium hover:text-primary hover:underline"
                                                >
                                                    {item.card.name}
                                                </Link>
                                            )}
                                            <div className="flex flex-wrap items-center gap-1 text-xs text-muted-foreground">
                                                #{item.card.number}
                                                {item.parallel && (
                                                    <span>
                                                        · {item.parallel.name}
                                                    </span>
                                                )}
                                                <CardFlags
                                                    card={item.card}
                                                    printRun={item.print_run}
                                                    serialNumber={
                                                        item.serial_number
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td className="px-3 py-2 whitespace-nowrap">
                                    {item.card.set.full_name}
                                </td>
                                <td className="px-3 py-2">
                                    <GradeBadge item={item} />
                                </td>
                                <td className="hidden px-3 py-2 text-muted-foreground md:table-cell">
                                    {item.location?.path ?? '—'}
                                    {item.storage_position &&
                                        ` · ${item.storage_position}`}
                                </td>
                                <td className="hidden px-3 py-2 xl:table-cell">
                                    <div className="flex flex-wrap gap-1">
                                        {item.tags?.map((tag) => (
                                            <TagChip
                                                key={tag.id}
                                                name={tag.name}
                                                color={tag.color}
                                            />
                                        ))}
                                    </div>
                                </td>
                                <td className="px-3 py-2 text-right text-muted-foreground tabular">
                                    {money(item.purchase_price)}
                                </td>
                                <td className="px-3 py-2 text-right font-medium tabular">
                                    {money(item.estimated_value)}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
