import { Head, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Search, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { CardArt } from '@/components/cards/card-art';
import { CardFlags } from '@/components/cards/card-flags';
import { GradeBadge } from '@/components/cards/grade-badge';
import { EmptyState } from '@/components/kit/empty-state';
import { NativeSelect } from '@/components/kit/field';
import { Pagination } from '@/components/kit/pagination';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { compactMoney, count, money, shortDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { CollectionItem, Option, Paginated } from '@/types';

type ShowcaseItem = {
    key: string;
    card: {
        number: string;
        name: string;
        subset: string | null;
        team: string | null;
        players: string[];
        is_rookie: boolean;
        is_autograph: boolean;
        is_memorabilia: boolean;
        is_short_print: boolean;
        set: {
            full_name: string;
            name: string;
            year: number;
            sport_label: string;
            manufacturer: string | null;
        };
    };
    parallel: { name: string; print_run: number | null } | null;
    print_run: number | null;
    serial_number: number | null;
    is_graded: boolean;
    grader: string | null;
    grade: string | null;
    condition_label: string | null;
    estimated_value: number | null;
    image: string | null;
    images: { url: string; side: string }[];
};

type Props = {
    showcase: {
        title: string;
        description: string | null;
        show_values: boolean;
        card_count: number;
        total_value: number | null;
        expires_at: string | null;
    };
    items: Paginated<ShowcaseItem>;
    query: string;
    sort: string;
    sorts: Option[];
};

/**
 * CardArt only reads the image, number, name, set and parallel, all of which a showcase item has.
 */
function artItem(
    item: ShowcaseItem,
): Pick<CollectionItem, 'image' | 'card' | 'parallel'> {
    return item as unknown as Pick<
        CollectionItem,
        'image' | 'card' | 'parallel'
    >;
}

export default function Showcase({
    showcase,
    items,
    query,
    sort,
    sorts,
}: Props) {
    const path = usePage().url.split('?')[0];
    const [search, setSearch] = useState(query);
    const [openIndex, setOpenIndex] = useState<number | null>(null);

    useEffect(() => setSearch(query), [query]);

    function visit(nextQuery: string, nextSort: string) {
        router.get(
            path,
            {
                q: nextQuery || undefined,
                sort: nextSort === sorts[0]?.value ? undefined : nextSort,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    const open = openIndex === null ? null : (items.data[openIndex] ?? null);

    return (
        <>
            <Head title={showcase.title}>
                <meta name="robots" content="noindex, nofollow" />
            </Head>

            <section className="bg-rail text-rail-foreground">
                <div className="mx-auto max-w-[1400px] px-4 pt-6 pb-10 sm:px-6 sm:pt-8 sm:pb-12">
                    <h1 className="font-display text-4xl leading-tight font-bold sm:text-5xl">
                        {showcase.title}
                    </h1>
                    {showcase.description && (
                        <p className="mt-2 max-w-2xl whitespace-pre-line text-rail-foreground/80">
                            {showcase.description}
                        </p>
                    )}
                    <dl className="mt-6 flex flex-wrap gap-x-10 gap-y-3">
                        <div>
                            <dt className="text-sm text-rail-muted">Cards</dt>
                            <dd className="font-display text-3xl font-bold tabular">
                                {showcase.card_count.toLocaleString()}
                            </dd>
                        </div>
                        {showcase.total_value !== null && (
                            <div>
                                <dt className="text-sm text-rail-muted">
                                    Estimated value
                                </dt>
                                <dd className="font-display text-3xl font-bold tabular">
                                    {compactMoney(showcase.total_value)}
                                </dd>
                            </div>
                        )}
                        {showcase.expires_at && (
                            <div>
                                <dt className="text-sm text-rail-muted">
                                    Link open until
                                </dt>
                                <dd className="font-display text-3xl font-bold tabular">
                                    {shortDate(showcase.expires_at)}
                                </dd>
                            </div>
                        )}
                    </dl>
                </div>
            </section>

            <div className="mx-auto max-w-[1400px] px-4 py-6 sm:px-6">
                <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <form
                        className="flex flex-1 gap-2 sm:max-w-xl"
                        onSubmit={(event) => {
                            event.preventDefault();
                            visit(search.trim(), sort);
                        }}
                    >
                        <div className="relative flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search players, sets, teams, years"
                                className="h-10 w-full rounded-lg border border-input bg-card pr-3 pl-9 shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                aria-label="Search these cards"
                            />
                        </div>
                        <Button type="submit" className="h-10">
                            Search
                        </Button>
                        {query && (
                            <Button
                                type="button"
                                variant="ghost"
                                className="h-10"
                                onClick={() => visit('', sort)}
                            >
                                <X /> Clear
                            </Button>
                        )}
                    </form>

                    <div className="flex items-center gap-2 sm:ml-auto">
                        <span className="text-sm text-muted-foreground tabular">
                            {count(items.total, 'card')}
                        </span>
                        <NativeSelect
                            value={sort}
                            onChange={(event) =>
                                visit(query, event.target.value)
                            }
                            className="h-10 w-auto"
                            aria-label="Sort"
                        >
                            {sorts.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                </div>

                {items.data.length === 0 ? (
                    <EmptyState
                        title={query ? 'No cards match' : 'Nothing to show yet'}
                        description={
                            query
                                ? 'Try a player’s last name, a set, or a year.'
                                : 'The owner hasn’t added any cards to this showcase.'
                        }
                    />
                ) : (
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6">
                        {items.data.map((item, index) => (
                            <button
                                key={item.key}
                                type="button"
                                onClick={() => setOpenIndex(index)}
                                className="group rounded-lg p-2 text-left transition-colors outline-none hover:bg-card focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <div className="relative transition-transform duration-200 group-hover:-translate-y-0.5">
                                    <CardArt item={artItem(item)} />
                                    <GradeBadge
                                        item={item}
                                        className="absolute top-2 left-2"
                                    />
                                </div>
                                <div className="mt-2 space-y-0.5 px-0.5">
                                    <div className="flex items-start justify-between gap-2">
                                        <p className="line-clamp-1 font-display text-[1.05rem] leading-tight font-semibold">
                                            {item.card.name}
                                        </p>
                                        {item.estimated_value !== null && (
                                            <p className="text-sm font-semibold tabular">
                                                {money(item.estimated_value)}
                                            </p>
                                        )}
                                    </div>
                                    <p className="line-clamp-1 text-xs text-muted-foreground">
                                        {item.card.set.full_name}{' '}
                                        <span className="text-foreground/80">
                                            #{item.card.number}
                                        </span>
                                    </p>
                                    <div className="flex min-h-5 flex-wrap items-center gap-1 pt-0.5">
                                        {item.parallel && (
                                            <span className="text-xs font-medium text-foreground/80">
                                                {item.parallel.name}
                                            </span>
                                        )}
                                        <CardFlags
                                            card={item.card}
                                            printRun={item.print_run}
                                            serialNumber={item.serial_number}
                                        />
                                    </div>
                                </div>
                            </button>
                        ))}
                    </div>
                )}

                <Pagination page={items} />
            </div>

            <Dialog
                open={open !== null}
                onOpenChange={(isOpen) => !isOpen && setOpenIndex(null)}
            >
                <DialogContent className="max-h-[92svh] overflow-y-auto sm:max-w-4xl">
                    {open && openIndex !== null && (
                        <CardDetail
                            key={open.key}
                            item={open}
                            onPrevious={
                                openIndex > 0
                                    ? () => setOpenIndex(openIndex - 1)
                                    : undefined
                            }
                            onNext={
                                openIndex < items.data.length - 1
                                    ? () => setOpenIndex(openIndex + 1)
                                    : undefined
                            }
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function CardDetail({
    item,
    onPrevious,
    onNext,
}: {
    item: ShowcaseItem;
    onPrevious?: () => void;
    onNext?: () => void;
}) {
    const [photo, setPhoto] = useState(0);
    const image = item.images[photo]?.url ?? null;

    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            if (event.key === 'ArrowLeft' && onPrevious) {
                onPrevious();
            }

            if (event.key === 'ArrowRight' && onNext) {
                onNext();
            }
        }

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [onPrevious, onNext]);

    const details: [string, React.ReactNode][] = [
        ['Set', item.card.set.full_name],
        ['Number', `#${item.card.number}`],
        ['Sport', item.card.set.sport_label],
        ...(item.card.players.length > 1
            ? [
                  ['Players', item.card.players.join(', ')] as [
                      string,
                      React.ReactNode,
                  ],
              ]
            : []),
        ...(item.card.team
            ? [['Team', item.card.team] as [string, React.ReactNode]]
            : []),
        ...(item.card.subset
            ? [['Subset', item.card.subset] as [string, React.ReactNode]]
            : []),
        ...(item.parallel
            ? [['Parallel', item.parallel.name] as [string, React.ReactNode]]
            : []),
        ...(item.print_run
            ? [
                  [
                      'Numbered',
                      item.serial_number
                          ? `${item.serial_number} of ${item.print_run}`
                          : `Out of ${item.print_run}`,
                  ] as [string, React.ReactNode],
              ]
            : []),
        ...(item.condition_label
            ? [
                  [
                      item.is_graded ? 'Grade' : 'Condition',
                      item.condition_label,
                  ] as [string, React.ReactNode],
              ]
            : []),
        ...(item.estimated_value !== null
            ? [
                  ['Estimated value', money(item.estimated_value)] as [
                      string,
                      React.ReactNode,
                  ],
              ]
            : []),
    ];

    return (
        <div className="grid gap-6 md:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
            <div>
                <div className="mx-auto max-w-[22rem]">
                    <CardArt item={{ ...artItem(item), image }} size="lg" />
                </div>
                {item.images.length > 1 && (
                    <div className="mt-3 flex justify-center gap-2">
                        {item.images.map((entry, index) => (
                            <button
                                key={entry.url}
                                type="button"
                                onClick={() => setPhoto(index)}
                                className={cn(
                                    'aspect-[5/7] w-12 overflow-hidden rounded-[3px] ring-offset-2 ring-offset-background',
                                    index === photo
                                        ? 'ring-2 ring-primary'
                                        : 'opacity-70 hover:opacity-100',
                                )}
                                aria-label={`Show ${entry.side} photo`}
                                aria-pressed={index === photo}
                            >
                                <img
                                    src={entry.url}
                                    alt=""
                                    className="h-full w-full object-cover"
                                />
                            </button>
                        ))}
                    </div>
                )}
            </div>

            <div className="min-w-0">
                <DialogTitle className="font-display text-3xl leading-tight font-bold">
                    {item.card.name}
                </DialogTitle>
                <DialogDescription className="mt-1">
                    {item.card.set.full_name} #{item.card.number}
                </DialogDescription>
                <div className="mt-3 flex flex-wrap items-center gap-2">
                    <GradeBadge item={item} />
                    <CardFlags
                        card={item.card}
                        printRun={item.print_run}
                        serialNumber={item.serial_number}
                    />
                </div>

                <dl className="mt-5 divide-y rounded-lg border">
                    {details.map(([label, value]) => (
                        <div
                            key={label}
                            className="grid grid-cols-[8rem_minmax(0,1fr)] gap-3 px-3 py-2 text-sm"
                        >
                            <dt className="text-muted-foreground">{label}</dt>
                            <dd className="font-medium">{value}</dd>
                        </div>
                    ))}
                </dl>

                <div className="mt-5 flex justify-between gap-2">
                    <Button
                        variant="outline"
                        onClick={onPrevious}
                        disabled={!onPrevious}
                    >
                        <ChevronLeft /> Previous card
                    </Button>
                    <Button
                        variant="outline"
                        onClick={onNext}
                        disabled={!onNext}
                    >
                        Next card <ChevronRight />
                    </Button>
                </div>
            </div>
        </div>
    );
}
