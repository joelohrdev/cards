import { Check, Search } from 'lucide-react';
import { useState } from 'react';
import { CardFlags } from '@/components/cards/card-flags';
import { Field, NativeSelect } from '@/components/kit/field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useLookup } from '@/hooks/use-lookup';
import { cn } from '@/lib/utils';
import catalog from '@/routes/catalog';
import type {
    CardSetSummary,
    CardSummary,
    Option,
    ParallelSummary,
} from '@/types';

export type NewCardDraft = {
    number: string;
    name: string;
    team: string;
    subset: string;
    is_rookie: boolean;
    is_autograph: boolean;
    is_memorabilia: boolean;
    is_short_print: boolean;
    print_run: string;
};

export type NewSetDraft = {
    sport: string;
    year: string;
    manufacturer: string;
    name: string;
};

export type PickedSet = CardSetSummary & { parallels: ParallelSummary[] };
export type PickedCard = CardSummary & {
    set: CardSetSummary;
    parallels: ParallelSummary[];
};

export const emptyCardDraft: NewCardDraft = {
    number: '',
    name: '',
    team: '',
    subset: '',
    is_rookie: false,
    is_autograph: false,
    is_memorabilia: false,
    is_short_print: false,
    print_run: '',
};

/**
 * Find a card in the shared catalog, or describe a new one (in an existing or new set).
 */
export function CardPicker({
    picked,
    onPick,
    mode,
    onModeChange,
    cardDraft,
    onCardDraftChange,
    pickedSet,
    onPickSet,
    setDraft,
    onSetDraftChange,
    sports,
    errors,
}: {
    picked: PickedCard | null;
    onPick: (card: PickedCard | null) => void;
    mode: 'existing' | 'new';
    onModeChange: (mode: 'existing' | 'new') => void;
    cardDraft: NewCardDraft;
    onCardDraftChange: (draft: NewCardDraft) => void;
    pickedSet: PickedSet | null;
    onPickSet: (set: PickedSet | null) => void;
    setDraft: NewSetDraft | null;
    onSetDraftChange: (draft: NewSetDraft | null) => void;
    sports: Option[];
    errors: Record<string, string | undefined>;
}) {
    const [query, setQuery] = useState('');
    const [setQueryText, setSetQueryText] = useState('');
    const cardResults = useLookup<{ cards: PickedCard[] }>(
        mode === 'existing' && !picked && query.trim().length >= 2
            ? catalog.lookup.cards.url({ query: { q: query.trim() } })
            : null,
    );
    const setResults = useLookup<{ sets: PickedSet[] }>(
        mode === 'new' &&
            !pickedSet &&
            setDraft === null &&
            setQueryText.trim().length >= 2
            ? catalog.lookup.sets.url({ query: { q: setQueryText.trim() } })
            : null,
    );

    const updateCard = (patch: Partial<NewCardDraft>) =>
        onCardDraftChange({ ...cardDraft, ...patch });

    return (
        <div className="space-y-4">
            <div
                className="flex rounded-lg border bg-muted p-1 text-sm"
                role="tablist"
            >
                {(['existing', 'new'] as const).map((option) => (
                    <button
                        key={option}
                        type="button"
                        role="tab"
                        aria-selected={mode === option}
                        onClick={() => onModeChange(option)}
                        className={cn(
                            'flex-1 rounded-md px-3 py-1.5 font-medium text-muted-foreground',
                            mode === option &&
                                'bg-card text-foreground shadow-xs',
                        )}
                    >
                        {option === 'existing' ? 'Find in catalog' : 'New card'}
                    </button>
                ))}
            </div>

            {mode === 'existing' && picked && (
                <div className="flex items-start justify-between gap-3 rounded-lg border border-primary/40 bg-primary/5 p-3">
                    <div>
                        <p className="font-display text-lg leading-tight font-semibold">
                            #{picked.number} {picked.name}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {picked.set.full_name}
                        </p>
                        <CardFlags
                            card={picked}
                            printRun={picked.print_run}
                            className="mt-1"
                        />
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => onPick(null)}
                    >
                        Change
                    </Button>
                </div>
            )}

            {mode === 'existing' && !picked && (
                <div>
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            autoFocus
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="e.g. 1989 upper deck griffey, or prizm #1"
                            className="h-11 pl-9"
                            aria-label="Search the catalog"
                        />
                    </div>
                    {errors.card && (
                        <p className="mt-1.5 text-sm text-destructive">
                            {errors.card}
                        </p>
                    )}
                    {cardResults.data && (
                        <ul className="mt-2 max-h-80 divide-y overflow-y-auto rounded-lg border bg-card">
                            {cardResults.data.cards.length === 0 && (
                                <li className="p-4 text-sm text-muted-foreground">
                                    Not in the catalog yet.{' '}
                                    <button
                                        type="button"
                                        className="font-medium text-primary hover:underline"
                                        onClick={() => onModeChange('new')}
                                    >
                                        Add it as a new card
                                    </button>
                                </li>
                            )}
                            {cardResults.data.cards.map((card) => (
                                <li key={card.id}>
                                    <button
                                        type="button"
                                        onClick={() => onPick(card)}
                                        className="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-accent"
                                    >
                                        <span className="w-14 shrink-0 font-display text-lg font-bold text-muted-foreground">
                                            #{card.number}
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate font-medium">
                                                {card.name}
                                            </span>
                                            <span className="block truncate text-xs text-muted-foreground">
                                                {card.set.full_name}
                                                {card.team && ` · ${card.team}`}
                                            </span>
                                        </span>
                                        <CardFlags
                                            card={card}
                                            printRun={card.print_run}
                                        />
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}

            {mode === 'new' && (
                <div className="space-y-4">
                    {pickedSet ? (
                        <div className="flex items-center justify-between gap-3 rounded-lg border bg-muted/50 p-3">
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Set
                                </p>
                                <p className="font-medium">
                                    {pickedSet.full_name}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => onPickSet(null)}
                            >
                                Change
                            </Button>
                        </div>
                    ) : setDraft ? (
                        <div className="space-y-3 rounded-lg border bg-muted/30 p-3">
                            <div className="flex items-center justify-between">
                                <p className="text-sm font-semibold">New set</p>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => onSetDraftChange(null)}
                                >
                                    Pick an existing set
                                </Button>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-4">
                                <Field
                                    label="Year"
                                    error={errors['new_set.year']}
                                >
                                    <Input
                                        inputMode="numeric"
                                        value={setDraft.year}
                                        onChange={(event) =>
                                            onSetDraftChange({
                                                ...setDraft,
                                                year: event.target.value,
                                            })
                                        }
                                        placeholder="1989"
                                    />
                                </Field>
                                <Field
                                    label="Brand"
                                    error={errors['new_set.manufacturer']}
                                >
                                    <Input
                                        value={setDraft.manufacturer}
                                        onChange={(event) =>
                                            onSetDraftChange({
                                                ...setDraft,
                                                manufacturer:
                                                    event.target.value,
                                            })
                                        }
                                        placeholder="Upper Deck"
                                    />
                                </Field>
                                <Field
                                    label="Set name"
                                    error={errors['new_set.name']}
                                    className="sm:col-span-2"
                                >
                                    <Input
                                        value={setDraft.name}
                                        onChange={(event) =>
                                            onSetDraftChange({
                                                ...setDraft,
                                                name: event.target.value,
                                            })
                                        }
                                        placeholder="Base, Chrome, Series 1…"
                                    />
                                </Field>
                                <Field
                                    label="Sport"
                                    error={errors['new_set.sport']}
                                >
                                    <NativeSelect
                                        value={setDraft.sport}
                                        onChange={(event) =>
                                            onSetDraftChange({
                                                ...setDraft,
                                                sport: event.target.value,
                                            })
                                        }
                                    >
                                        {sports.map((sport) => (
                                            <option
                                                key={sport.value}
                                                value={sport.value}
                                            >
                                                {sport.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </Field>
                            </div>
                        </div>
                    ) : (
                        <div>
                            <Field label="Set" error={errors.card_set_id}>
                                <Input
                                    value={setQueryText}
                                    onChange={(event) =>
                                        setSetQueryText(event.target.value)
                                    }
                                    placeholder="Search sets, e.g. 2023 topps chrome"
                                />
                            </Field>
                            {setResults.data && (
                                <ul className="mt-2 max-h-64 divide-y overflow-y-auto rounded-lg border bg-card">
                                    {setResults.data.sets.map((set) => (
                                        <li key={set.id}>
                                            <button
                                                type="button"
                                                onClick={() => onPickSet(set)}
                                                className="flex w-full items-center justify-between px-3 py-2 text-left hover:bg-accent"
                                            >
                                                <span>{set.full_name}</span>
                                                <span className="text-xs text-muted-foreground">
                                                    {set.sport_label}
                                                </span>
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <button
                                type="button"
                                className="mt-2 text-sm font-medium text-primary hover:underline"
                                onClick={() =>
                                    onSetDraftChange({
                                        sport: sports[0]?.value ?? 'baseball',
                                        year: '',
                                        manufacturer: '',
                                        name: '',
                                    })
                                }
                            >
                                The set isn’t listed. Create it
                            </button>
                        </div>
                    )}

                    <div className="grid gap-3 sm:grid-cols-6">
                        <Field
                            label="Card #"
                            error={errors['card.number']}
                            className="sm:col-span-1"
                        >
                            <Input
                                value={cardDraft.number}
                                onChange={(event) =>
                                    updateCard({ number: event.target.value })
                                }
                                placeholder="1"
                            />
                        </Field>
                        <Field
                            label="Player(s) / card name"
                            error={errors['card.name']}
                            hint="Separate multiple players with “/”."
                            className="sm:col-span-3"
                        >
                            <Input
                                value={cardDraft.name}
                                onChange={(event) =>
                                    updateCard({ name: event.target.value })
                                }
                                placeholder="Ken Griffey Jr."
                            />
                        </Field>
                        <Field label="Team" className="sm:col-span-2">
                            <Input
                                value={cardDraft.team}
                                onChange={(event) =>
                                    updateCard({ team: event.target.value })
                                }
                                placeholder="Seattle Mariners"
                            />
                        </Field>
                        <Field
                            label="Subset / insert"
                            className="sm:col-span-3"
                        >
                            <Input
                                value={cardDraft.subset}
                                onChange={(event) =>
                                    updateCard({ subset: event.target.value })
                                }
                                placeholder="Star Rookie"
                            />
                        </Field>
                        <Field
                            label="Numbered to"
                            className="sm:col-span-1"
                            hint="Print run"
                        >
                            <Input
                                inputMode="numeric"
                                value={cardDraft.print_run}
                                onChange={(event) =>
                                    updateCard({
                                        print_run: event.target.value,
                                    })
                                }
                                placeholder="99"
                            />
                        </Field>
                        <div className="flex flex-wrap items-center gap-x-4 gap-y-2 pt-6 sm:col-span-2">
                            {(
                                [
                                    ['is_rookie', 'Rookie'],
                                    ['is_autograph', 'Auto'],
                                    ['is_memorabilia', 'Relic'],
                                    ['is_short_print', 'SP'],
                                ] as const
                            ).map(([key, label]) => (
                                <label
                                    key={key}
                                    className="flex items-center gap-1.5 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        checked={cardDraft[key]}
                                        onChange={(event) =>
                                            updateCard({
                                                [key]: event.target.checked,
                                            })
                                        }
                                        className="size-4 accent-[var(--primary)]"
                                    />
                                    {label}
                                </label>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {mode === 'existing' && picked && (
                <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <Check className="size-3.5 text-primary" /> Card details
                    come from the shared catalog.
                </p>
            )}
        </div>
    );
}
