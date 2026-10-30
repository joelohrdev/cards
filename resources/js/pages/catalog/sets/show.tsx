import { Form, Head, Link, router } from '@inertiajs/react';
import {
    Check,
    Heart,
    ListChecks,
    Pencil,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { CardFlags } from '@/components/cards/card-flags';
import { ChecklistHelp } from '@/components/catalog/checklist-help';
import { TagInput } from '@/components/collection/tag-input';
import { Field, NativeSelect, Textarea } from '@/components/kit/field';
import { PageHeader } from '@/components/kit/page-header';
import { Panel } from '@/components/kit/panel';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { percent } from '@/lib/format';
import { cn } from '@/lib/utils';
import cards from '@/routes/cards';
import collection from '@/routes/collection';
import sets from '@/routes/sets';
import wantList from '@/routes/want-list';
import type { CardSetSummary, FormOptions, ParallelSummary } from '@/types';

type ChecklistCard = {
    id: number;
    number: string;
    name: string;
    subset: string | null;
    team: string | null;
    players: string[];
    is_rookie: boolean;
    is_autograph: boolean;
    is_memorabilia: boolean;
    is_short_print: boolean;
    print_run: number | null;
    owned: { id: number; parallel: string | null; condition: string | null }[];
    wanted: boolean;
};

type Props = {
    set: CardSetSummary & {
        notes: string | null;
        parallels: ParallelSummary[];
    };
    cards: ChecklistCard[];
    options: FormOptions;
    manufacturers: string[];
};

export default function SetShow({
    set,
    cards: checklist,
    options,
    manufacturers,
}: Props) {
    const [filter, setFilter] = useState<'all' | 'missing' | 'owned'>('all');
    const [ticking, setTicking] = useState(false);
    const [ticked, setTicked] = useState<number[]>([]);
    const [parallelId, setParallelId] = useState('');
    const [condition, setCondition] = useState('nm');
    const [locationId, setLocationId] = useState('');
    const [tags, setTags] = useState<string[]>([]);
    const [editingSet, setEditingSet] = useState(false);
    const [editingCard, setEditingCard] = useState<ChecklistCard | null>(null);
    const [pasting, setPasting] = useState(false);

    const ownedCount = checklist.filter((card) => card.owned.length > 0).length;
    const total = Math.max(set.card_count ?? 0, checklist.length);
    const visible = useMemo(
        () =>
            checklist.filter((card) =>
                filter === 'missing'
                    ? card.owned.length === 0
                    : filter === 'owned'
                      ? card.owned.length > 0
                      : true,
            ),
        [checklist, filter],
    );

    function toggle(id: number) {
        setTicked((current) =>
            current.includes(id)
                ? current.filter((entry) => entry !== id)
                : [...current, id],
        );
    }

    function addOwned() {
        router.post(
            sets.owned.store.url(set.id),
            {
                card_ids: ticked,
                parallel_id: parallelId || null,
                raw_condition: condition || null,
                storage_location_id: locationId || null,
                tags,
            } as never,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setTicked([]);
                    setTicking(false);
                },
            },
        );
    }

    function addWanted() {
        router.post(
            wantList.store.url(),
            { card_ids: ticked, parallel_id: parallelId || null } as never,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setTicked([]);
                    setTicking(false);
                },
            },
        );
    }

    return (
        <>
            <Head title={set.full_name} />
            <PageHeader
                eyebrow={
                    <Link href={sets.index()} className="hover:text-foreground">
                        ‹ Sets & checklists
                    </Link>
                }
                title={set.full_name}
                description={`${set.sport_label}${set.notes ? ` · ${set.notes}` : ''}`}
                actions={
                    <>
                        <Button
                            variant="outline"
                            onClick={() => setEditingSet(true)}
                        >
                            <Pencil /> Edit set
                        </Button>
                        <Button asChild variant="outline">
                            <Link
                                href={collection.index({
                                    query: { set: set.id, status: ['all'] },
                                })}
                            >
                                See my cards
                            </Link>
                        </Button>
                    </>
                }
            />

            <div className="mb-6 grid gap-4 rounded-lg border bg-card p-5 sm:grid-cols-[1fr_auto] sm:items-center">
                <div>
                    <div className="flex items-baseline justify-between text-sm">
                        <span className="font-display text-3xl font-bold tabular">
                            {ownedCount}
                            <span className="text-muted-foreground">
                                /{total || '?'}
                            </span>
                        </span>
                        <span className="text-muted-foreground tabular">
                            {percent(ownedCount, total)}% complete
                        </span>
                    </div>
                    <div className="mt-2 h-3 rounded-full bg-muted">
                        <div
                            className="h-3 rounded-full bg-primary"
                            style={{
                                width: `${Math.max(1, percent(ownedCount, total))}%`,
                            }}
                        />
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    {checklist.length > 0 && (
                        <Button
                            variant={ticking ? 'default' : 'outline'}
                            onClick={() => {
                                setTicking(!ticking);
                                setTicked([]);
                            }}
                        >
                            <ListChecks />{' '}
                            {ticking ? 'Stop ticking' : 'Tick off cards'}
                        </Button>
                    )}
                    <Button
                        variant="outline"
                        onClick={() => setPasting(!pasting)}
                    >
                        <Plus />{' '}
                        {checklist.length
                            ? 'Add checklist cards'
                            : 'Paste checklist'}
                    </Button>
                </div>
            </div>

            {(pasting || checklist.length === 0) && (
                <Panel title="Paste checklist" className="mb-6">
                    <Form
                        action={sets.checklist.store.url(set.id)}
                        method="post"
                        options={{ preserveScroll: true }}
                        onSuccess={() => setPasting(false)}
                        resetOnSuccess
                        className="grid gap-3"
                    >
                        {({ errors, processing }) => (
                            <>
                                <Field
                                    label="Checklist"
                                    hint={<ChecklistHelp />}
                                    error={errors.checklist}
                                >
                                    <Textarea
                                        name="checklist"
                                        rows={10}
                                        className="font-mono text-xs"
                                        placeholder={
                                            '1 Rickey Henderson - Oakland Athletics\n2 Roger Clemens - Boston Red Sox RC'
                                        }
                                    />
                                </Field>
                                <div>
                                    <Button type="submit" disabled={processing}>
                                        Save checklist
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </Panel>
            )}

            <div className="grid gap-6 xl:grid-cols-[1fr_18rem]">
                <div className="min-w-0">
                    {checklist.length > 0 && (
                        <div
                            className="mb-3 flex items-center gap-1 text-sm"
                            role="tablist"
                        >
                            {(
                                [
                                    ['all', `All ${checklist.length}`],
                                    [
                                        'missing',
                                        `Missing ${checklist.length - ownedCount}`,
                                    ],
                                    ['owned', `Owned ${ownedCount}`],
                                ] as const
                            ).map(([value, label]) => (
                                <button
                                    key={value}
                                    type="button"
                                    role="tab"
                                    aria-selected={filter === value}
                                    onClick={() => setFilter(value)}
                                    className={cn(
                                        'rounded-full px-3 py-1',
                                        filter === value
                                            ? 'bg-foreground text-background'
                                            : 'hover:bg-accent',
                                    )}
                                >
                                    {label}
                                </button>
                            ))}
                            {ticking && (
                                <button
                                    type="button"
                                    className="ml-auto text-sm font-medium text-primary hover:underline"
                                    onClick={() =>
                                        setTicked(
                                            visible.map((card) => card.id),
                                        )
                                    }
                                >
                                    Tick all {visible.length} shown
                                </button>
                            )}
                        </div>
                    )}

                    {checklist.length > 0 && (
                        <div className="overflow-hidden rounded-lg border bg-card">
                            <ul className="divide-y">
                                {visible.map((card) => {
                                    const isTicked = ticked.includes(card.id);
                                    const owned = card.owned.length > 0;

                                    return (
                                        <li
                                            key={card.id}
                                            className={cn(
                                                'grid grid-cols-[2rem_4rem_1fr_auto] items-center gap-3 px-3 py-2',
                                                isTicked && 'bg-primary/10',
                                                ticking && 'cursor-pointer',
                                            )}
                                            onClick={
                                                ticking
                                                    ? () => toggle(card.id)
                                                    : undefined
                                            }
                                        >
                                            <span
                                                className={cn(
                                                    'flex size-6 items-center justify-center rounded border-2',
                                                    owned
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : 'border-border',
                                                    isTicked &&
                                                        !owned &&
                                                        'border-primary bg-primary/20',
                                                )}
                                                aria-label={
                                                    owned
                                                        ? 'Owned'
                                                        : 'Not owned'
                                                }
                                            >
                                                {(owned || isTicked) && (
                                                    <Check
                                                        className="size-3.5"
                                                        strokeWidth={3}
                                                    />
                                                )}
                                            </span>
                                            <span className="font-display text-lg font-bold text-muted-foreground tabular">
                                                {card.number}
                                            </span>
                                            <span className="min-w-0">
                                                <span className="flex flex-wrap items-center gap-1.5">
                                                    <span className="truncate font-medium">
                                                        {card.name}
                                                    </span>
                                                    <CardFlags
                                                        card={card}
                                                        printRun={
                                                            card.print_run
                                                        }
                                                    />
                                                    {card.wanted && (
                                                        <Heart
                                                            className="size-3.5 fill-slab text-slab"
                                                            aria-label="On your want list"
                                                        />
                                                    )}
                                                </span>
                                                <span className="block truncate text-xs text-muted-foreground">
                                                    {[card.team, card.subset]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                    {owned && (
                                                        <span className="text-foreground/70">
                                                            {card.team ||
                                                            card.subset
                                                                ? ' · '
                                                                : ''}
                                                            {card.owned.length >
                                                            1
                                                                ? `${card.owned.length} copies`
                                                                : [
                                                                      card
                                                                          .owned[0]
                                                                          .parallel,
                                                                      card
                                                                          .owned[0]
                                                                          .condition,
                                                                  ]
                                                                      .filter(
                                                                          Boolean,
                                                                      )
                                                                      .join(
                                                                          ', ',
                                                                      ) ||
                                                                  'Owned'}
                                                        </span>
                                                    )}
                                                </span>
                                            </span>
                                            {!ticking && (
                                                <span className="flex items-center gap-1">
                                                    {owned ? (
                                                        <Button
                                                            asChild
                                                            variant="ghost"
                                                            size="sm"
                                                        >
                                                            <Link
                                                                href={collection.show(
                                                                    card
                                                                        .owned[0]
                                                                        .id,
                                                                )}
                                                            >
                                                                View
                                                            </Link>
                                                        </Button>
                                                    ) : (
                                                        <Button
                                                            asChild
                                                            variant="ghost"
                                                            size="sm"
                                                        >
                                                            <Link
                                                                href={collection.create(
                                                                    {
                                                                        query: {
                                                                            card: card.id,
                                                                        },
                                                                    },
                                                                )}
                                                            >
                                                                Add
                                                            </Link>
                                                        </Button>
                                                    )}
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                        onClick={() =>
                                                            setEditingCard(card)
                                                        }
                                                        aria-label={`Edit ${card.name}`}
                                                    >
                                                        <Pencil className="size-3.5" />
                                                    </Button>
                                                </span>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    )}
                </div>

                <Panel title="Parallels">
                    {set.parallels.length === 0 ? (
                        <p className="mb-3 text-sm text-muted-foreground">
                            No parallels yet. Add the colors and refractors this
                            set comes in.
                        </p>
                    ) : (
                        <ul className="mb-4 divide-y text-sm">
                            {set.parallels.map((parallel) => (
                                <li
                                    key={parallel.id}
                                    className="flex items-center justify-between py-1.5"
                                >
                                    <span>
                                        {parallel.name}
                                        {parallel.print_run && (
                                            <span className="text-muted-foreground tabular">
                                                {' '}
                                                /{parallel.print_run}
                                            </span>
                                        )}
                                    </span>
                                    <button
                                        type="button"
                                        className="text-muted-foreground hover:text-destructive"
                                        aria-label={`Remove ${parallel.name}`}
                                        onClick={() =>
                                            router.delete(
                                                sets.parallels.destroy.url({
                                                    set: set.id,
                                                    parallel: parallel.id,
                                                }),
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <X className="size-4" />
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                    <Form
                        action={sets.parallels.store.url(set.id)}
                        method="post"
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="grid grid-cols-[1fr_4.5rem] gap-2"
                    >
                        {({ errors, processing }) => (
                            <>
                                <Input
                                    name="name"
                                    placeholder="Gold"
                                    aria-label="Parallel name"
                                />
                                <Input
                                    name="print_run"
                                    inputMode="numeric"
                                    placeholder="/50"
                                    aria-label="Print run"
                                />
                                {errors.name && (
                                    <p className="col-span-2 text-sm text-destructive">
                                        {errors.name}
                                    </p>
                                )}
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    disabled={processing}
                                    className="col-span-2"
                                >
                                    Add parallel
                                </Button>
                            </>
                        )}
                    </Form>
                </Panel>
            </div>

            {ticking && ticked.length > 0 && (
                <div className="no-print fixed inset-x-0 bottom-4 z-40 flex justify-center px-4">
                    <div className="flex w-full max-w-5xl flex-wrap items-end gap-3 rounded-xl bg-rail p-3 text-rail-foreground shadow-2xl">
                        <p className="self-center font-display text-lg font-semibold tabular">
                            {ticked.length} ticked
                        </p>
                        <label className="grid gap-1 text-xs text-rail-muted">
                            Parallel
                            <NativeSelect
                                value={parallelId}
                                onChange={(event) =>
                                    setParallelId(event.target.value)
                                }
                                className="w-40 border-white/15 bg-rail-active text-rail-foreground"
                            >
                                <option value="">Base</option>
                                {set.parallels.map((parallel) => (
                                    <option
                                        key={parallel.id}
                                        value={parallel.id}
                                    >
                                        {parallel.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </label>
                        <label className="grid gap-1 text-xs text-rail-muted">
                            Condition
                            <NativeSelect
                                value={condition}
                                onChange={(event) =>
                                    setCondition(event.target.value)
                                }
                                className="w-40 border-white/15 bg-rail-active text-rail-foreground"
                            >
                                {options.conditions.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </label>
                        <label className="grid gap-1 text-xs text-rail-muted">
                            Storage
                            <NativeSelect
                                value={locationId}
                                onChange={(event) =>
                                    setLocationId(event.target.value)
                                }
                                className="w-44 border-white/15 bg-rail-active text-rail-foreground"
                            >
                                <option value="">Not placed</option>
                                {options.locations.map((location) => (
                                    <option
                                        key={location.id}
                                        value={location.id}
                                    >
                                        {location.path}
                                    </option>
                                ))}
                            </NativeSelect>
                        </label>
                        <div className="grid min-w-48 flex-1 gap-1 text-xs text-rail-muted">
                            Tags
                            <div className="text-foreground">
                                <TagInput
                                    value={tags}
                                    onChange={setTags}
                                    suggestions={[]}
                                />
                            </div>
                        </div>
                        <div className="ml-auto flex gap-2">
                            <Button
                                variant="ghost"
                                className="text-rail-foreground hover:bg-rail-active hover:text-rail-foreground"
                                onClick={addWanted}
                            >
                                <Heart /> Want these
                            </Button>
                            <Button variant="secondary" onClick={addOwned}>
                                Add {ticked.length} to collection
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            <Dialog open={editingSet} onOpenChange={setEditingSet}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit set</DialogTitle>
                    </DialogHeader>
                    <Form
                        {...sets.update.form(set.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setEditingSet(false)}
                        className="grid gap-3 sm:grid-cols-2"
                    >
                        {({ errors, processing }) => (
                            <>
                                <Field label="Year" error={errors.year}>
                                    <Input
                                        name="year"
                                        defaultValue={set.year}
                                    />
                                </Field>
                                <Field label="Season" error={errors.season}>
                                    <Input
                                        name="season"
                                        defaultValue={set.season ?? ''}
                                    />
                                </Field>
                                <Field
                                    label="Brand"
                                    error={errors.manufacturer}
                                >
                                    <Input
                                        name="manufacturer"
                                        list="set-manufacturers"
                                        defaultValue={set.manufacturer ?? ''}
                                    />
                                    <datalist id="set-manufacturers">
                                        {manufacturers.map((name) => (
                                            <option key={name} value={name} />
                                        ))}
                                    </datalist>
                                </Field>
                                <Field label="Sport" error={errors.sport}>
                                    <NativeSelect
                                        name="sport"
                                        defaultValue={set.sport}
                                    >
                                        {options.sports.map((sport) => (
                                            <option
                                                key={sport.value}
                                                value={sport.value}
                                            >
                                                {sport.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </Field>
                                <Field label="Set name" error={errors.name}>
                                    <Input
                                        name="name"
                                        defaultValue={set.name}
                                    />
                                </Field>
                                <Field
                                    label="Cards in set"
                                    error={errors.card_count}
                                >
                                    <Input
                                        name="card_count"
                                        defaultValue={set.card_count ?? ''}
                                    />
                                </Field>
                                <Field
                                    label="Notes"
                                    error={errors.notes}
                                    className="sm:col-span-2"
                                >
                                    <Textarea
                                        name="notes"
                                        defaultValue={set.notes ?? ''}
                                    />
                                </Field>
                                {errors.set && (
                                    <p className="text-sm text-destructive sm:col-span-2">
                                        {errors.set}
                                    </p>
                                )}
                                <DialogFooter className="sm:col-span-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="mr-auto text-destructive"
                                        onClick={() =>
                                            router.delete(
                                                sets.destroy.url(set.id),
                                            )
                                        }
                                    >
                                        <Trash2 /> Delete set
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        Save set
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={editingCard !== null}
                onOpenChange={(open) => !open && setEditingCard(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit checklist card</DialogTitle>
                    </DialogHeader>
                    {editingCard && (
                        <Form
                            action={cards.update.url(editingCard.id)}
                            method="patch"
                            options={{ preserveScroll: true }}
                            onSuccess={() => setEditingCard(null)}
                            transform={(data) => ({
                                ...data,
                                players: String(data.players ?? '')
                                    .split('/')
                                    .map((name) => name.trim())
                                    .filter(Boolean),
                                is_rookie: data.is_rookie === 'on',
                                is_autograph: data.is_autograph === 'on',
                                is_memorabilia: data.is_memorabilia === 'on',
                                is_short_print: data.is_short_print === 'on',
                            })}
                            className="grid gap-3 sm:grid-cols-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <Field label="Card #" error={errors.number}>
                                        <Input
                                            name="number"
                                            defaultValue={editingCard.number}
                                        />
                                    </Field>
                                    <Field
                                        label="Name"
                                        error={errors.name}
                                        className="sm:col-span-3"
                                    >
                                        <Input
                                            name="name"
                                            defaultValue={editingCard.name}
                                        />
                                    </Field>
                                    <Field
                                        label="Players"
                                        hint="Separate with “/”."
                                        className="sm:col-span-2"
                                    >
                                        <Input
                                            name="players"
                                            defaultValue={editingCard.players.join(
                                                ' / ',
                                            )}
                                        />
                                    </Field>
                                    <Field
                                        label="Team"
                                        className="sm:col-span-2"
                                    >
                                        <Input
                                            name="team"
                                            defaultValue={
                                                editingCard.team ?? ''
                                            }
                                        />
                                    </Field>
                                    <Field
                                        label="Subset"
                                        className="sm:col-span-3"
                                    >
                                        <Input
                                            name="subset"
                                            defaultValue={
                                                editingCard.subset ?? ''
                                            }
                                        />
                                    </Field>
                                    <Field
                                        label="Numbered to"
                                        error={errors.print_run}
                                    >
                                        <Input
                                            name="print_run"
                                            defaultValue={
                                                editingCard.print_run ?? ''
                                            }
                                        />
                                    </Field>
                                    <div className="flex flex-wrap gap-4 sm:col-span-4">
                                        {(
                                            [
                                                ['is_rookie', 'Rookie'],
                                                ['is_autograph', 'Auto'],
                                                ['is_memorabilia', 'Relic'],
                                                [
                                                    'is_short_print',
                                                    'Short print',
                                                ],
                                            ] as const
                                        ).map(([key, label]) => (
                                            <label
                                                key={key}
                                                className="flex items-center gap-1.5 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    name={key}
                                                    defaultChecked={
                                                        editingCard[key]
                                                    }
                                                    className="size-4 accent-[var(--primary)]"
                                                />
                                                {label}
                                            </label>
                                        ))}
                                    </div>
                                    {errors.card && (
                                        <p className="text-sm text-destructive sm:col-span-4">
                                            {errors.card}
                                        </p>
                                    )}
                                    <DialogFooter className="sm:col-span-4">
                                        {editingCard.owned.length === 0 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                className="mr-auto text-destructive"
                                                onClick={() =>
                                                    router.delete(
                                                        cards.destroy.url(
                                                            editingCard.id,
                                                        ),
                                                        {
                                                            preserveScroll: true,
                                                            onSuccess: () =>
                                                                setEditingCard(
                                                                    null,
                                                                ),
                                                        },
                                                    )
                                                }
                                            >
                                                <Trash2 /> Remove from checklist
                                            </Button>
                                        )}
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save card
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
