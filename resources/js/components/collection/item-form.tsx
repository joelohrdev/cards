import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    CardPicker,
    emptyCardDraft,
} from '@/components/collection/card-picker';
import type {
    NewCardDraft,
    NewSetDraft,
    PickedCard,
    PickedSet,
} from '@/components/collection/card-picker';
import { CardSearchHelp } from '@/components/collection/card-search-help';
import {
    emptyGradingPlan,
    GradingPlanFields,
    gradingPayload,
} from '@/components/collection/grading-plan-fields';
import type {
    GradingPlan,
    OpenGradingSubmission,
} from '@/components/collection/grading-plan-fields';
import { PhotoSlot } from '@/components/collection/photo-slot';
import { TagInput } from '@/components/collection/tag-input';
import { CertLookup } from '@/components/grading/cert-lookup';
import { IdentifyFromPhoto } from '@/components/identify/identify-from-photo';
import {
    Field,
    MoneyInput,
    NativeSelect,
    Textarea,
} from '@/components/kit/field';
import { Panel } from '@/components/kit/panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dollarsInput } from '@/lib/format';
import { cn } from '@/lib/utils';
import collection from '@/routes/collection';
import type {
    CertLookupResult,
    CollectionItem,
    FormOptions,
    ParallelSummary,
    Option,
    PhotoSuggestion,
} from '@/types';
import { SuggestInput } from '@/components/kit/suggest-input';

type ItemFormData = {
    card_id: number | null;
    card_set_id: number | null;
    new_set: NewSetDraft | null;
    card:
        | (Omit<NewCardDraft, 'print_run'> & { print_run: string | null })
        | null;
    parallel_id: string;
    parallel_name: string;
    parallel_print_run: string;
    serial_number: string;
    is_graded: boolean;
    grader: string;
    grade: string;
    cert_number: string;
    raw_condition: string;
    status: string;
    purchase_price: string;
    purchase_date: string;
    purchase_source: string;
    estimated_value: string;
    storage_location_id: string;
    storage_position: string;
    notes: string;
    tags: string[];
    quantity: string;
    add_another: boolean;
    photo: File | null;
    back_photo: File | null;
    grading_plan: GradingPlan;
};

/**
 * Compare names the way the server's ExistingNameMatcher does: "Gold Refractor" and
 * "gold-refractor" are the same parallel.
 */
function nameKey(name: string): string {
    return name
        .normalize('NFD')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '');
}

/**
 * Shared add/edit form for a card in the collection.
 */
export function ItemForm({
    options,
    item,
    parallels: initialParallels = [],
    prefillCard = null,
    gradingSubmissions = [],
    gradingStatuses = [],
}: {
    options: FormOptions;
    item?: CollectionItem;
    parallels?: ParallelSummary[];
    prefillCard?: PickedCard | null;
    gradingSubmissions?: OpenGradingSubmission[];
    gradingStatuses?: Option[];
}) {
    const editing = item !== undefined;
    const [mode, setMode] = useState<'existing' | 'new'>('existing');
    const [picked, setPicked] = useState<PickedCard | null>(
        item ? { ...item.card, parallels: initialParallels } : prefillCard,
    );
    const [pickedSet, setPickedSet] = useState<PickedSet | null>(null);
    const [cardDraft, setCardDraft] = useState<NewCardDraft>(emptyCardDraft);
    const [setDraft, setSetDraft] = useState<NewSetDraft | null>(null);
    const [newParallel, setNewParallel] = useState(false);

    const form = useForm<ItemFormData>({
        card_id: item?.card.id ?? prefillCard?.id ?? null,
        card_set_id: null,
        new_set: null,
        card: null,
        parallel_id: item?.parallel ? String(item.parallel.id) : '',
        parallel_name: '',
        parallel_print_run: '',
        serial_number: item?.serial_number ? String(item.serial_number) : '',
        is_graded: item?.is_graded ?? false,
        grader: item?.grader ?? 'PSA',
        grade: item?.grade ?? '',
        cert_number: item?.cert_number ?? '',
        raw_condition: item?.raw_condition ?? 'nm',
        status: item?.status ?? 'owned',
        purchase_price: dollarsInput(item?.purchase_price),
        purchase_date: item?.purchase_date ?? '',
        purchase_source: item?.purchase_source ?? '',
        estimated_value: dollarsInput(item?.estimated_value),
        storage_location_id: item?.location ? String(item.location.id) : '',
        storage_position: item?.storage_position ?? '',
        notes: item?.notes ?? '',
        tags: item?.tags?.map((tag) => tag.name) ?? [],
        quantity: '1',
        add_another: false,
        photo: null,
        back_photo: null,
        grading_plan: emptyGradingPlan,
    });

    const { data, setData, errors, processing } = form;
    const errorBag = errors as Record<string, string | undefined>;
    const parallels: ParallelSummary[] =
        mode === 'existing'
            ? (picked?.parallels ?? [])
            : (pickedSet?.parallels ?? []);
    const selectedParallel = parallels.find(
        (parallel) => String(parallel.id) === data.parallel_id,
    );
    const printRun = newParallel
        ? Number(data.parallel_print_run) || null
        : (selectedParallel?.print_run ??
          (mode === 'existing'
              ? picked?.print_run
              : Number(cardDraft.print_run) || null));

    /**
     * Fill the form from a card identified in photos; they become the card's front and back images.
     */
    function applySuggestion(
        suggestion: PhotoSuggestion,
        photo: File,
        backPhoto: File | null,
    ) {
        const match = suggestion.matches[0] ?? null;
        const knownParallels =
            match?.parallels ?? suggestion.set?.parallels ?? [];
        const knownParallel = suggestion.parallel
            ? knownParallels.find(
                  (parallel) =>
                      nameKey(parallel.name) ===
                      nameKey(suggestion.parallel ?? ''),
              )
            : undefined;

        if (match) {
            setMode('existing');
            setPicked(match);
        } else {
            setMode('new');
            setPickedSet(suggestion.set);
            setSetDraft(
                suggestion.set
                    ? null
                    : {
                          sport:
                              suggestion.sport ??
                              options.sports[0]?.value ??
                              'baseball',
                          year: suggestion.year ? String(suggestion.year) : '',
                          manufacturer: suggestion.manufacturer ?? '',
                          name: suggestion.set_name ?? '',
                      },
            );
            setCardDraft({
                ...emptyCardDraft,
                number: suggestion.number ?? '',
                name: suggestion.name ?? '',
                team: suggestion.team ?? '',
                is_rookie: suggestion.is_rookie,
                is_autograph: suggestion.is_autograph,
                is_memorabilia: suggestion.is_memorabilia,
                print_run: suggestion.print_run
                    ? String(suggestion.print_run)
                    : '',
            });
        }

        setNewParallel(Boolean(suggestion.parallel) && !knownParallel);
        form.setData((current) => ({
            ...current,
            photo,
            back_photo: backPhoto ?? current.back_photo,
            parallel_id: knownParallel ? String(knownParallel.id) : '',
            parallel_name: knownParallel ? '' : (suggestion.parallel ?? ''),
            parallel_print_run:
                suggestion.print_run && !knownParallel
                    ? String(suggestion.print_run)
                    : '',
            serial_number: suggestion.serial_number
                ? String(suggestion.serial_number)
                : current.serial_number,
            is_graded: suggestion.is_graded,
            grader: suggestion.grader ?? current.grader,
            grade: suggestion.grade ?? '',
            cert_number: suggestion.cert_number ?? '',
        }));
    }

    /**
     * Fill grade details (and, for a brand-new card, the card itself) from a cert lookup.
     */
    function applyCert(result: CertLookupResult) {
        form.setData((current) => ({
            ...current,
            grade: result.grade ?? current.grade,
        }));

        if (
            mode === 'new' &&
            cardDraft.number === '' &&
            cardDraft.name === ''
        ) {
            setCardDraft({
                ...cardDraft,
                number: result.card_number ?? '',
                name: result.player ?? '',
            });

            if (!pickedSet && setDraft === null) {
                setSetDraft({
                    sport:
                        result.sport ?? options.sports[0]?.value ?? 'baseball',
                    year: result.year ? String(result.year) : '',
                    manufacturer: result.brand ?? '',
                    name: result.set_name ?? '',
                });
            }
        }
    }

    /**
     * After "Add and enter another", the next card joins the same submission, including
     * one that was just started for the previous card.
     */
    function keepGradingPlanForNextCard(submissions: OpenGradingSubmission[]) {
        const plan = data.grading_plan;

        if (plan.plan === 'none' || plan.submission_id !== '') {
            return;
        }

        const started = submissions
            .filter(
                (submission) =>
                    submission.is_sent === (plan.plan === 'sent') &&
                    submission.grader === plan.grader,
            )
            .sort((a, b) => b.id - a.id)[0];

        if (started) {
            setData('grading_plan', {
                ...plan,
                submission_id: String(started.id),
            });
        }
    }

    function submit(event: React.FormEvent, addAnother = false) {
        event.preventDefault();

        form.transform(({ grading_plan: gradingPlan, ...current }) => ({
            ...current,
            grading:
                editing || current.is_graded
                    ? null
                    : gradingPayload(gradingPlan),
            card_id: mode === 'existing' ? (picked?.id ?? null) : null,
            card_set_id: mode === 'new' ? (pickedSet?.id ?? null) : null,
            new_set: mode === 'new' && !pickedSet ? setDraft : null,
            card:
                mode === 'new'
                    ? { ...cardDraft, print_run: cardDraft.print_run || null }
                    : null,
            parallel_id: newParallel ? '' : current.parallel_id,
            parallel_name: newParallel ? current.parallel_name : '',
            add_another: addAnother,
        }));

        if (editing) {
            form.put(collection.update.url(item.id), { preserveScroll: true });
        } else {
            form.post(collection.store.url(), {
                preserveScroll: true,
                onSuccess: (page) => {
                    if (addAnother) {
                        keepGradingPlanForNextCard(
                            (page.props.gradingSubmissions ??
                                []) as OpenGradingSubmission[],
                        );
                        form.setData((current) => ({
                            ...current,
                            serial_number: '',
                            cert_number: '',
                            grade: '',
                            notes: '',
                            photo: null,
                            back_photo: null,
                        }));
                        setCardDraft((current) => ({
                            ...emptyCardDraft,
                            team: current.team,
                        }));
                        setPicked(null);
                    }
                },
            });
        }
    }

    return (
        <form
            onSubmit={(event) => submit(event)}
            className="grid gap-6 xl:grid-cols-[1fr_22rem]"
        >
            <div className="space-y-6">
                <Panel
                    title={
                        <span className="flex items-center gap-1">
                            Which card?
                            {!editing && <CardSearchHelp />}
                        </span>
                    }
                >
                    {editing ? (
                        <div>
                            <p className="font-display text-xl font-semibold">
                                #{item.card.number} {item.card.name}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {item.card.set.full_name}
                            </p>
                            <p className="mt-2 text-xs text-muted-foreground">
                                To fix the card’s checklist details, edit it
                                from the set page.
                            </p>
                        </div>
                    ) : (
                        <>
                            {data.photo && (
                                <p className="mb-3 rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground">
                                    Filled in from your photos. Check the
                                    details; the photos will be saved with the
                                    card.
                                </p>
                            )}
                            <CardPicker
                                picked={picked}
                                onPick={(card) => {
                                    setPicked(card);
                                    setData('parallel_id', '');
                                }}
                                mode={mode}
                                onModeChange={setMode}
                                cardDraft={cardDraft}
                                onCardDraftChange={setCardDraft}
                                pickedSet={pickedSet}
                                onPickSet={(set) => {
                                    setPickedSet(set);
                                    setData('parallel_id', '');
                                }}
                                setDraft={setDraft}
                                onSetDraftChange={setSetDraft}
                                scanAction={
                                    <IdentifyFromPhoto
                                        onResult={applySuggestion}
                                    />
                                }
                                sports={options.sports}
                                errors={errorBag}
                            />
                        </>
                    )}
                </Panel>

                {!editing && (
                    <Panel title="Photos">
                        <div className="grid max-w-sm grid-cols-2 gap-4">
                            <PhotoSlot
                                label="Front"
                                file={data.photo}
                                onChange={(file) => setData('photo', file)}
                                error={errors.photo}
                            />
                            <PhotoSlot
                                label="Back"
                                file={data.back_photo}
                                onChange={(file) => setData('back_photo', file)}
                                error={errors.back_photo}
                            />
                        </div>
                        <p className="mt-3 text-sm text-muted-foreground">
                            JPG, PNG or WebP. You can add more photos from the
                            card’s page later.
                        </p>
                    </Panel>
                )}

                <Panel title="Version">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field
                            label="Parallel"
                            error={errors.parallel_id ?? errors.parallel_name}
                            className="sm:col-span-2"
                        >
                            {newParallel ? (
                                <div className="flex gap-2">
                                    <SuggestInput
                                        field="parallel"
                                        setId={
                                            mode === 'existing'
                                                ? (picked?.set.id ?? null)
                                                : (pickedSet?.id ?? null)
                                        }
                                        value={data.parallel_name}
                                        onChange={(name) =>
                                            setData('parallel_name', name)
                                        }
                                        onPick={(name) => {
                                            const existing = parallels.find(
                                                (parallel) =>
                                                    parallel.name === name,
                                            );

                                            if (existing) {
                                                setNewParallel(false);
                                                form.setData((current) => ({
                                                    ...current,
                                                    parallel_id: String(
                                                        existing.id,
                                                    ),
                                                    parallel_name: '',
                                                    parallel_print_run: '',
                                                }));
                                            } else {
                                                setData('parallel_name', name);
                                            }
                                        }}
                                        placeholder="Silver Prizm, Gold Refractor…"
                                    />
                                    <Input
                                        inputMode="numeric"
                                        value={data.parallel_print_run}
                                        onChange={(event) =>
                                            setData(
                                                'parallel_print_run',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="/ run"
                                        className="w-24"
                                        aria-label="Print run"
                                    />
                                </div>
                            ) : (
                                <NativeSelect
                                    value={data.parallel_id}
                                    onChange={(event) =>
                                        setData(
                                            'parallel_id',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">Base card</option>
                                    {parallels.map((parallel) => (
                                        <option
                                            key={parallel.id}
                                            value={parallel.id}
                                        >
                                            {parallel.name}
                                            {parallel.print_run
                                                ? ` /${parallel.print_run}`
                                                : ''}
                                        </option>
                                    ))}
                                </NativeSelect>
                            )}
                            <button
                                type="button"
                                className="justify-self-start text-xs font-medium text-primary hover:underline"
                                onClick={() => setNewParallel(!newParallel)}
                            >
                                {newParallel
                                    ? 'Choose an existing parallel'
                                    : 'Add a parallel that isn’t listed'}
                            </button>
                        </Field>
                        <Field
                            label="Serial number"
                            error={errors.serial_number}
                            hint={
                                printRun
                                    ? `Out of ${printRun}`
                                    : 'For numbered cards'
                            }
                        >
                            <Input
                                inputMode="numeric"
                                value={data.serial_number}
                                onChange={(event) =>
                                    setData('serial_number', event.target.value)
                                }
                                placeholder="23"
                            />
                        </Field>
                    </div>
                </Panel>

                <Panel title="Condition">
                    <div className="mb-4 inline-flex rounded-lg border bg-muted p-1 text-sm">
                        {[false, true].map((graded) => (
                            <button
                                key={String(graded)}
                                type="button"
                                onClick={() => setData('is_graded', graded)}
                                className={cn(
                                    'rounded-md px-4 py-1.5 font-medium text-muted-foreground',
                                    data.is_graded === graded &&
                                        'bg-card text-foreground shadow-xs',
                                )}
                            >
                                {graded ? 'Graded' : 'Raw'}
                            </button>
                        ))}
                    </div>

                    {data.is_graded ? (
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field label="Grader" error={errors.grader}>
                                <NativeSelect
                                    value={data.grader}
                                    onChange={(event) =>
                                        setData('grader', event.target.value)
                                    }
                                >
                                    {options.graders.map((grader) => (
                                        <option
                                            key={grader.value}
                                            value={grader.value}
                                        >
                                            {grader.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field label="Grade" error={errors.grade}>
                                <Input
                                    value={data.grade}
                                    onChange={(event) =>
                                        setData('grade', event.target.value)
                                    }
                                    placeholder="10, 9.5, Authentic"
                                />
                            </Field>
                            <Field
                                label="Cert number"
                                error={errors.cert_number}
                            >
                                <div className="flex gap-2">
                                    <Input
                                        value={data.cert_number}
                                        onChange={(event) =>
                                            setData(
                                                'cert_number',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="12345678"
                                    />
                                    <CertLookup
                                        grader={data.grader}
                                        certNumber={data.cert_number}
                                        onResult={applyCert}
                                    />
                                </div>
                            </Field>
                        </div>
                    ) : (
                        <div className="flex flex-wrap gap-1.5">
                            {options.conditions.map((condition) => (
                                <button
                                    key={condition.value}
                                    type="button"
                                    onClick={() =>
                                        setData(
                                            'raw_condition',
                                            condition.value,
                                        )
                                    }
                                    className={cn(
                                        'rounded-full border px-3 py-1 text-sm',
                                        data.raw_condition === condition.value
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'bg-card hover:bg-accent',
                                    )}
                                >
                                    {condition.label}
                                </button>
                            ))}
                        </div>
                    )}

                    {!editing && !data.is_graded && (
                        <GradingPlanFields
                            plan={data.grading_plan}
                            onChange={(plan) => setData('grading_plan', plan)}
                            submissions={gradingSubmissions}
                            graders={options.graders}
                            statuses={gradingStatuses}
                            errors={errorBag}
                        />
                    )}
                </Panel>

                <Panel title="Notes & tags">
                    <div className="grid gap-4">
                        <Field
                            label="Tags"
                            htmlFor="item-tags"
                            error={errors.tags}
                        >
                            <TagInput
                                id="item-tags"
                                value={data.tags}
                                onChange={(tags) => setData('tags', tags)}
                                suggestions={options.tags}
                            />
                        </Field>
                        <Field label="Notes" error={errors.notes}>
                            <Textarea
                                value={data.notes}
                                onChange={(event) =>
                                    setData('notes', event.target.value)
                                }
                                placeholder="Centering, surface wear, where it came from…"
                            />
                        </Field>
                    </div>
                </Panel>
            </div>

            <div className="space-y-6">
                <Panel title="Money">
                    <div className="grid gap-4">
                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Paid" error={errors.purchase_price}>
                                <MoneyInput
                                    value={data.purchase_price}
                                    onChange={(event) =>
                                        setData(
                                            'purchase_price',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="0.00"
                                />
                            </Field>
                            <Field
                                label="Worth now"
                                error={errors.estimated_value}
                            >
                                <MoneyInput
                                    value={data.estimated_value}
                                    onChange={(event) =>
                                        setData(
                                            'estimated_value',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="0.00"
                                />
                            </Field>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <Field
                                label="Bought on"
                                error={errors.purchase_date}
                            >
                                <Input
                                    type="date"
                                    value={data.purchase_date}
                                    onChange={(event) =>
                                        setData(
                                            'purchase_date',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="Bought from"
                                error={errors.purchase_source}
                            >
                                <SuggestInput
                                    field="purchase_source"
                                    value={data.purchase_source}
                                    onChange={(value) =>
                                        setData('purchase_source', value)
                                    }
                                    placeholder="eBay, show…"
                                />
                            </Field>
                        </div>
                    </div>
                </Panel>

                <Panel title="Storage">
                    <div className="grid gap-4">
                        <Field
                            label="Location"
                            error={errors.storage_location_id}
                            hint={
                                options.locations.length === 0
                                    ? 'Set up boxes and binders under Storage.'
                                    : undefined
                            }
                        >
                            <NativeSelect
                                value={data.storage_location_id}
                                onChange={(event) =>
                                    setData(
                                        'storage_location_id',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">Not placed yet</option>
                                {options.locations.map((location) => (
                                    <option
                                        key={location.id}
                                        value={location.id}
                                    >
                                        {location.path}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <Field
                            label="Slot / page / row"
                            error={errors.storage_position}
                        >
                            <Input
                                value={data.storage_position}
                                onChange={(event) =>
                                    setData(
                                        'storage_position',
                                        event.target.value,
                                    )
                                }
                                placeholder="Page 4, slot 7"
                            />
                        </Field>
                    </div>
                </Panel>

                {editing ? (
                    <Panel title="Status">
                        <NativeSelect
                            value={data.status}
                            onChange={(event) =>
                                setData('status', event.target.value)
                            }
                            aria-label="Status"
                        >
                            {options.statuses.map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </Panel>
                ) : (
                    <Panel title="How many copies?">
                        <Field
                            label="Quantity"
                            error={errors.quantity}
                            hint="Each copy is tracked separately."
                        >
                            <Input
                                type="number"
                                min={1}
                                max={100}
                                value={data.quantity}
                                onChange={(event) =>
                                    setData('quantity', event.target.value)
                                }
                            />
                        </Field>
                    </Panel>
                )}

                <div className="sticky bottom-4 flex flex-col gap-2 rounded-lg border bg-card p-3 shadow-lg">
                    {Object.keys(errors).length > 0 && (
                        <p className="text-sm text-destructive">
                            Couldn’t save: {Object.values(errorBag)[0]}
                        </p>
                    )}
                    <Button type="submit" size="lg" disabled={processing}>
                        {editing ? 'Save changes' : 'Add to collection'}
                    </Button>
                    {!editing && (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={(event) => submit(event, true)}
                        >
                            Add and enter another
                        </Button>
                    )}
                </div>
            </div>
        </form>
    );
}
