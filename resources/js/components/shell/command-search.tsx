import { router } from '@inertiajs/react';
import { BookOpenCheck, CornerDownLeft, Search, User } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { CardThumb } from '@/components/cards/card-thumb';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useLookup } from '@/hooks/use-lookup';
import { money } from '@/lib/format';
import { cn } from '@/lib/utils';
import { search } from '@/routes';
import collection from '@/routes/collection';
import sets from '@/routes/sets';
import type { CollectionItem } from '@/types';

type SearchResults = {
    items: CollectionItem[];
    total: number;
    sets: { id: number; name: string; sport: string }[];
    players: { id: number; name: string; sport: string }[];
};

type Result = {
    key: string;
    href: string;
};

/**
 * The global "find a card" palette, opened with ⌘K or "/".
 */
export function CommandSearch({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const trimmed = query.trim();
    const { data, loading } = useLookup<SearchResults>(
        open && trimmed.length >= 2
            ? search.url({ query: { q: trimmed } })
            : null,
        150,
    );

    const results = useMemo<Result[]>(() => {
        if (!data) {
            return [];
        }

        return [
            ...data.items.map((item) => ({
                key: `item-${item.id}`,
                href: collection.show.url(item.id),
            })),
            ...data.sets.map((set) => ({
                key: `set-${set.id}`,
                href: sets.show.url(set.id),
            })),
            ...data.players.map((player) => ({
                key: `player-${player.id}`,
                href: collection.index.url({
                    query: { player: player.id, status: ['all'] },
                }),
            })),
        ];
    }, [data]);

    useEffect(() => setActive(0), [data]);

    function go(href: string) {
        onOpenChange(false);
        setQuery('');
        router.visit(href);
    }

    function searchAll() {
        go(collection.index.url({ query: { q: trimmed } }));
    }

    function onKeyDown(event: React.KeyboardEvent<HTMLInputElement>) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((index) => Math.min(index + 1, results.length));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((index) => Math.max(index - 1, 0));
        } else if (event.key === 'Enter') {
            event.preventDefault();

            if (active < results.length && results[active]) {
                go(results[active].href);
            } else if (trimmed !== '') {
                searchAll();
            }
        }
    }

    const keyIndex = (key: string) =>
        results.findIndex((result) => result.key === key);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="top-[12%] translate-y-0 gap-0 overflow-hidden p-0 sm:max-w-2xl [&>button]:hidden">
                <DialogTitle className="sr-only">Search your cards</DialogTitle>
                <div className="flex items-center gap-3 border-b px-4">
                    <Search className="size-5 text-muted-foreground" />
                    <input
                        autoFocus
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={onKeyDown}
                        placeholder="1986 fleer jordan, psa 10 rc, #311, /99 auto…"
                        className="h-14 flex-1 bg-transparent text-lg outline-none placeholder:text-muted-foreground/70"
                        aria-label="Search your cards"
                    />
                    {loading && (
                        <span className="text-xs text-muted-foreground">
                            Searching…
                        </span>
                    )}
                </div>

                <div className="max-h-[60vh] overflow-y-auto p-2">
                    {trimmed.length < 2 && (
                        <div className="space-y-3 p-4 text-sm text-muted-foreground">
                            <p>
                                Type a player, set, year, or card number.
                                Shorthand works too:
                            </p>
                            <ul className="grid grid-cols-2 gap-x-6 gap-y-1.5">
                                {[
                                    ['psa 10', 'graded PSA 10s'],
                                    ['rc', 'rookie cards'],
                                    ['#57', 'card number 57'],
                                    ['/99', 'numbered to 99'],
                                    ['auto', 'autographs'],
                                    ['raw', 'ungraded cards'],
                                ].map(([example, meaning]) => (
                                    <li key={example}>
                                        <button
                                            type="button"
                                            className="font-display font-semibold text-foreground hover:text-primary"
                                            onClick={() =>
                                                setQuery((current) =>
                                                    `${current} ${example}`.trim(),
                                                )
                                            }
                                        >
                                            {example}
                                        </button>{' '}
                                        {meaning}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {data && data.items.length > 0 && (
                        <section>
                            <h3 className="px-2 pt-2 pb-1 text-xs font-semibold text-muted-foreground">
                                Your cards
                            </h3>
                            {data.items.map((item) => {
                                const index = keyIndex(`item-${item.id}`);

                                return (
                                    <button
                                        type="button"
                                        key={item.id}
                                        onMouseEnter={() => setActive(index)}
                                        onClick={() =>
                                            go(collection.show.url(item.id))
                                        }
                                        className={cn(
                                            'flex w-full items-center gap-3 rounded-md px-2 py-1.5 text-left',
                                            active === index && 'bg-accent',
                                        )}
                                    >
                                        <CardThumb
                                            item={item}
                                            className="w-9"
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate font-medium">
                                                {item.card.name}
                                            </span>
                                            <span className="block truncate text-xs text-muted-foreground">
                                                {item.card.set.full_name} #
                                                {item.card.number}
                                                {item.parallel &&
                                                    ` · ${item.parallel.name}`}
                                                {item.condition_label &&
                                                    ` · ${item.condition_label}`}
                                            </span>
                                        </span>
                                        <span className="text-sm tabular">
                                            {money(item.estimated_value, '')}
                                        </span>
                                    </button>
                                );
                            })}
                        </section>
                    )}

                    {data && data.sets.length > 0 && (
                        <section>
                            <h3 className="px-2 pt-3 pb-1 text-xs font-semibold text-muted-foreground">
                                Sets
                            </h3>
                            {data.sets.map((set) => {
                                const index = keyIndex(`set-${set.id}`);

                                return (
                                    <button
                                        type="button"
                                        key={set.id}
                                        onMouseEnter={() => setActive(index)}
                                        onClick={() =>
                                            go(sets.show.url(set.id))
                                        }
                                        className={cn(
                                            'flex w-full items-center gap-3 rounded-md px-2 py-2 text-left',
                                            active === index && 'bg-accent',
                                        )}
                                    >
                                        <BookOpenCheck className="size-4 text-muted-foreground" />
                                        <span className="flex-1 truncate">
                                            {set.name}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {set.sport}
                                        </span>
                                    </button>
                                );
                            })}
                        </section>
                    )}

                    {data && data.players.length > 0 && (
                        <section>
                            <h3 className="px-2 pt-3 pb-1 text-xs font-semibold text-muted-foreground">
                                Players
                            </h3>
                            {data.players.map((player) => {
                                const index = keyIndex(`player-${player.id}`);

                                return (
                                    <button
                                        type="button"
                                        key={player.id}
                                        onMouseEnter={() => setActive(index)}
                                        onClick={() =>
                                            go(
                                                collection.index.url({
                                                    query: {
                                                        player: player.id,
                                                        status: ['all'],
                                                    },
                                                }),
                                            )
                                        }
                                        className={cn(
                                            'flex w-full items-center gap-3 rounded-md px-2 py-2 text-left',
                                            active === index && 'bg-accent',
                                        )}
                                    >
                                        <User className="size-4 text-muted-foreground" />
                                        <span className="flex-1 truncate">
                                            {player.name}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {player.sport}
                                        </span>
                                    </button>
                                );
                            })}
                        </section>
                    )}

                    {data && trimmed.length >= 2 && (
                        <button
                            type="button"
                            onMouseEnter={() => setActive(results.length)}
                            onClick={searchAll}
                            className={cn(
                                'mt-1 flex w-full items-center gap-3 rounded-md px-2 py-2.5 text-left text-sm',
                                active === results.length && 'bg-accent',
                            )}
                        >
                            <CornerDownLeft className="size-4 text-muted-foreground" />
                            <span className="flex-1">
                                {data.total > 0
                                    ? `See all ${data.total.toLocaleString()} matching cards`
                                    : `No cards match “${trimmed}”. Search the full collection anyway`}
                            </span>
                        </button>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
