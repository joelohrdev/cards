import { Head, Link, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { CardFlags } from '@/components/cards/card-flags';
import { EmptyState } from '@/components/kit/empty-state';
import { NativeSelect } from '@/components/kit/field';
import { PageHeader } from '@/components/kit/page-header';
import { Button } from '@/components/ui/button';
import { dollarsInput, money } from '@/lib/format';
import collection from '@/routes/collection';
import sets from '@/routes/sets';
import wantList from '@/routes/want-list';
import type { CardSetSummary, CardSummary } from '@/types';

type Entry = {
    id: number;
    card: CardSummary & { set: CardSetSummary };
    parallel: string | null;
    max_price: number | null;
    priority: number;
    notes: string | null;
};

const priorities = [
    { value: 3, label: 'Must have' },
    { value: 2, label: 'Want' },
    { value: 1, label: 'Someday' },
];

export default function WantList({ entries }: { entries: Entry[] }) {
    function update(
        entry: Entry,
        patch: Partial<{ priority: number; max_price: string }>,
    ) {
        router.patch(
            wantList.update.url(entry.id),
            {
                priority: entry.priority,
                max_price: dollarsInput(entry.max_price),
                notes: entry.notes,
                ...patch,
            } as never,
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Want list" />
            <PageHeader
                title="Want list"
                description="Cards you’re hunting, with the most you’ll pay. Add cards from any set checklist with “Want these”. Open it on your phone at a show."
            />

            {entries.length === 0 ? (
                <EmptyState
                    title="Your want list is empty"
                    description="Open a set, tick the cards you’re missing, and choose “Want these”."
                    action={
                        <Button asChild>
                            <Link href={sets.index()}>Browse sets</Link>
                        </Button>
                    }
                />
            ) : (
                <ul className="divide-y overflow-hidden rounded-lg border bg-card">
                    {entries.map((entry) => (
                        <li
                            key={entry.id}
                            className="grid gap-3 px-4 py-3 sm:grid-cols-[1fr_10rem_9rem_auto] sm:items-center"
                        >
                            <div className="min-w-0">
                                <p className="flex flex-wrap items-center gap-2 font-medium">
                                    <span className="font-display text-lg text-muted-foreground">
                                        #{entry.card.number}
                                    </span>
                                    {entry.card.name}
                                    <CardFlags
                                        card={entry.card}
                                        printRun={entry.card.print_run}
                                    />
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    <Link
                                        href={sets.show(entry.card.set.id)}
                                        className="hover:underline"
                                    >
                                        {entry.card.set.full_name}
                                    </Link>
                                    {entry.parallel && ` · ${entry.parallel}`}
                                </p>
                            </div>
                            <NativeSelect
                                value={entry.priority}
                                onChange={(event) =>
                                    update(entry, {
                                        priority: Number(event.target.value),
                                    })
                                }
                                aria-label="Priority"
                            >
                                {priorities.map((priority) => (
                                    <option
                                        key={priority.value}
                                        value={priority.value}
                                    >
                                        {priority.label}
                                    </option>
                                ))}
                            </NativeSelect>
                            <label className="flex items-center gap-2 text-sm">
                                <span className="text-muted-foreground">
                                    Max
                                </span>
                                <input
                                    defaultValue={dollarsInput(entry.max_price)}
                                    onBlur={(event) =>
                                        event.target.value !==
                                            dollarsInput(entry.max_price) &&
                                        update(entry, {
                                            max_price: event.target.value,
                                        })
                                    }
                                    inputMode="decimal"
                                    placeholder={money(null)}
                                    className="h-9 w-full rounded-md border border-input bg-card px-2 tabular"
                                    aria-label="Maximum price"
                                />
                            </label>
                            <div className="flex gap-1">
                                <Button asChild size="sm" variant="outline">
                                    <Link
                                        href={collection.create({
                                            query: { card: entry.card.id },
                                        })}
                                    >
                                        Got it
                                    </Link>
                                </Button>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    className="size-8 hover:text-destructive"
                                    aria-label="Remove from want list"
                                    onClick={() =>
                                        router.delete(
                                            wantList.destroy.url(entry.id),
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}
