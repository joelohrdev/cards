import { Form, Head, Link, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { CardThumb } from '@/components/cards/card-thumb';
import { StatusPill } from '@/components/grading/status-pill';
import {
    Field,
    MoneyInput,
    NativeSelect,
    Textarea,
} from '@/components/kit/field';
import { PageHeader } from '@/components/kit/page-header';
import { Panel } from '@/components/kit/panel';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useLookup } from '@/hooks/use-lookup';
import { count, dollarsInput, money, shortDate } from '@/lib/format';
import collection from '@/routes/collection';
import grading from '@/routes/grading';
import type { CollectionItem, Option } from '@/types';
import type { SubmissionSummary } from './index';

type SubmissionItem = {
    item: CollectionItem;
    declared_value: number | null;
    fee: number;
    resulting_grade: string | null;
    resulting_cert_number: string | null;
};

type Props = {
    submission: SubmissionSummary & {
        notes: string | null;
        is_completed: boolean;
        is_with_grader: boolean;
    };
    items: SubmissionItem[];
    totals: { declared: number; fees: number; shipping: number };
    graders: Option[];
    statuses: Option[];
};

export default function GradingShow({
    submission,
    items,
    totals,
    graders,
    statuses,
}: Props) {
    const [confirmDelete, setConfirmDelete] = useState(false);
    const title = [submission.grader, submission.service_level]
        .filter(Boolean)
        .join(' · ');

    return (
        <>
            <Head title={`${title} submission`} />

            <PageHeader
                eyebrow={
                    <Link
                        href={grading.index()}
                        className="hover:text-foreground"
                    >
                        Grading
                    </Link>
                }
                title={
                    <span className="flex flex-wrap items-center gap-3">
                        {title}
                        <StatusPill
                            status={submission.status}
                            label={submission.status_label}
                        />
                    </span>
                }
                description={
                    <span className="tabular">
                        {count(items.length, 'card')} ·{' '}
                        {money(totals.fees + totals.shipping)} in fees and
                        shipping
                        {submission.reference && ` · #${submission.reference}`}
                    </span>
                }
                actions={
                    <Button
                        variant="outline"
                        onClick={() => setConfirmDelete(true)}
                    >
                        <Trash2 /> Delete
                    </Button>
                }
            />

            <div className="grid gap-6 lg:grid-cols-[1fr_22rem]">
                <div className="space-y-6">
                    <CardsPanel
                        submission={submission}
                        items={items}
                        totals={totals}
                    />
                    {!submission.is_completed && (
                        <AddCardsPanel submission={submission} />
                    )}
                    {!submission.is_completed && items.length > 0 && (
                        <ResultsPanel
                            submission={submission}
                            items={items}
                            totals={totals}
                        />
                    )}
                </div>

                <DetailsPanel
                    submission={submission}
                    graders={graders}
                    statuses={statuses}
                />
            </div>

            <Dialog open={confirmDelete} onOpenChange={setConfirmDelete}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete this submission?</DialogTitle>
                        <DialogDescription>
                            The cards stay in your collection.{' '}
                            {submission.is_with_grader &&
                                'Cards marked “At grading” go back to “Owned”. '}
                            {submission.is_completed &&
                                'Grades already recorded on the cards are kept.'}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmDelete(false)}
                        >
                            Keep it
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                router.delete(
                                    grading.destroy.url(submission.id),
                                )
                            }
                        >
                            Delete submission
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function DetailsPanel({
    submission,
    graders,
    statuses,
}: {
    submission: Props['submission'];
    graders: Option[];
    statuses: Option[];
}) {
    return (
        <Panel title="Details" className="self-start">
            <Form
                action={grading.update.url(submission.id)}
                method="patch"
                options={{ preserveScroll: true }}
                className="grid gap-4"
            >
                {({ errors, processing }) => (
                    <>
                        {submission.is_completed ? (
                            <p className="text-sm text-muted-foreground">
                                Completed
                                {submission.returned_on &&
                                    ` on ${shortDate(submission.returned_on)}`}
                                {submission.turnaround_days !== null &&
                                    ` after ${count(submission.turnaround_days, 'day')}`}
                                .
                            </p>
                        ) : (
                            <Field
                                label="Status"
                                htmlFor="status"
                                error={errors.status}
                                hint="Once it’s sent, its cards show as “At grading”."
                            >
                                <NativeSelect
                                    id="status"
                                    name="status"
                                    defaultValue={submission.status}
                                >
                                    {statuses.map((status) => (
                                        <option
                                            key={status.value}
                                            value={status.value}
                                        >
                                            {status.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                        )}
                        <div className="grid grid-cols-2 gap-3">
                            <Field
                                label="Grader"
                                htmlFor="grader"
                                error={errors.grader}
                            >
                                <NativeSelect
                                    id="grader"
                                    name="grader"
                                    defaultValue={submission.grader}
                                >
                                    {graders.map((grader) => (
                                        <option
                                            key={grader.value}
                                            value={grader.value}
                                        >
                                            {grader.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field
                                label="Service level"
                                htmlFor="service_level"
                                error={errors.service_level}
                            >
                                <Input
                                    id="service_level"
                                    name="service_level"
                                    defaultValue={
                                        submission.service_level ?? ''
                                    }
                                />
                            </Field>
                            <Field
                                label="Sent on"
                                htmlFor="submitted_on"
                                error={errors.submitted_on}
                            >
                                <Input
                                    id="submitted_on"
                                    type="date"
                                    name="submitted_on"
                                    defaultValue={submission.submitted_on ?? ''}
                                />
                            </Field>
                            <Field
                                label="Returned on"
                                htmlFor="returned_on"
                                error={errors.returned_on}
                            >
                                <Input
                                    id="returned_on"
                                    type="date"
                                    name="returned_on"
                                    defaultValue={submission.returned_on ?? ''}
                                />
                            </Field>
                            <Field
                                label="Submission number"
                                htmlFor="reference"
                                error={errors.reference}
                            >
                                <Input
                                    id="reference"
                                    name="reference"
                                    defaultValue={submission.reference ?? ''}
                                />
                            </Field>
                            <Field
                                label="Shipping"
                                htmlFor="shipping_cost"
                                error={errors.shipping_cost}
                            >
                                <MoneyInput
                                    id="shipping_cost"
                                    name="shipping_cost"
                                    defaultValue={dollarsInput(
                                        submission.shipping_cost,
                                    )}
                                />
                            </Field>
                        </div>
                        <Field
                            label="Notes"
                            htmlFor="notes"
                            error={errors.notes}
                        >
                            <Textarea
                                id="notes"
                                name="notes"
                                defaultValue={submission.notes ?? ''}
                                className="min-h-16"
                            />
                        </Field>
                        <Button type="submit" disabled={processing}>
                            Save details
                        </Button>
                    </>
                )}
            </Form>
        </Panel>
    );
}

function CardsPanel({
    submission,
    items,
    totals,
}: {
    submission: Props['submission'];
    items: SubmissionItem[];
    totals: Props['totals'];
}) {
    if (items.length === 0) {
        return (
            <Panel title="Cards">
                <p className="text-sm text-muted-foreground">
                    No cards yet. Search your raw cards below and add the ones
                    you’re sending.
                </p>
            </Panel>
        );
    }

    return (
        <Panel title="Cards" bodyClassName="p-0">
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="text-left text-xs text-muted-foreground">
                        <tr className="border-b">
                            <th className="px-4 py-2 font-medium">Card</th>
                            {submission.is_completed ? (
                                <>
                                    <th className="px-4 py-2 font-medium">
                                        Grade
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Cert
                                    </th>
                                </>
                            ) : (
                                <>
                                    <th className="w-32 px-4 py-2 font-medium">
                                        Declared value
                                    </th>
                                    <th className="w-28 px-4 py-2 font-medium">
                                        Fee
                                    </th>
                                    <th className="w-10" />
                                </>
                            )}
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {items.map((entry) => (
                            <tr key={entry.item.id}>
                                <td className="px-4 py-2">
                                    <div className="flex items-center gap-3">
                                        <CardThumb item={entry.item} />
                                        <Link
                                            href={collection.show(
                                                entry.item.id,
                                            )}
                                            className="hover:text-primary hover:underline"
                                        >
                                            {entry.item.title}
                                        </Link>
                                    </div>
                                </td>
                                {submission.is_completed ? (
                                    <>
                                        <td className="px-4 py-2 font-medium">
                                            {entry.resulting_grade
                                                ? `${submission.grader} ${entry.resulting_grade}`
                                                : 'Not graded'}
                                        </td>
                                        <td className="px-4 py-2 text-muted-foreground tabular">
                                            {entry.resulting_cert_number ?? '—'}
                                        </td>
                                    </>
                                ) : (
                                    <ItemMoneyCells
                                        submissionId={submission.id}
                                        entry={entry}
                                    />
                                )}
                            </tr>
                        ))}
                    </tbody>
                    <tfoot className="border-t text-sm">
                        <tr>
                            <td className="px-4 py-2 text-muted-foreground">
                                Totals
                                {totals.shipping > 0 &&
                                    ` · plus ${money(totals.shipping)} shipping`}
                            </td>
                            {submission.is_completed ? (
                                <td colSpan={2} className="px-4 py-2 tabular">
                                    {money(totals.fees)} in fees
                                </td>
                            ) : (
                                <>
                                    <td className="px-4 py-2 font-medium tabular">
                                        {money(totals.declared)}
                                    </td>
                                    <td className="px-4 py-2 font-medium tabular">
                                        {money(totals.fees)}
                                    </td>
                                    <td />
                                </>
                            )}
                        </tr>
                    </tfoot>
                </table>
            </div>
        </Panel>
    );
}

/**
 * Inline-editable declared value and fee; saves when the field loses focus.
 */
function ItemMoneyCells({
    submissionId,
    entry,
}: {
    submissionId: number;
    entry: SubmissionItem;
}) {
    const [declared, setDeclared] = useState(
        dollarsInput(entry.declared_value),
    );
    const [fee, setFee] = useState(dollarsInput(entry.fee));

    function save() {
        if (
            declared === dollarsInput(entry.declared_value) &&
            fee === dollarsInput(entry.fee)
        ) {
            return;
        }

        router.patch(
            grading.items.update.url({
                submission: submissionId,
                item: entry.item.id,
            }),
            { declared_value: declared, fee },
            { preserveScroll: true, preserveState: true },
        );
    }

    return (
        <>
            <td className="px-4 py-2">
                <MoneyInput
                    aria-label={`Declared value for ${entry.item.title}`}
                    value={declared}
                    onChange={(event) => setDeclared(event.target.value)}
                    onBlur={save}
                />
            </td>
            <td className="px-4 py-2">
                <MoneyInput
                    aria-label={`Grading fee for ${entry.item.title}`}
                    value={fee}
                    onChange={(event) => setFee(event.target.value)}
                    onBlur={save}
                />
            </td>
            <td className="px-2 py-2 text-right">
                <button
                    type="button"
                    className="text-muted-foreground hover:text-destructive"
                    aria-label={`Remove ${entry.item.title} from this submission`}
                    onClick={() =>
                        router.delete(
                            grading.items.destroy.url({
                                submission: submissionId,
                                item: entry.item.id,
                            }),
                            {
                                preserveScroll: true,
                            },
                        )
                    }
                >
                    <Trash2 className="size-4" />
                </button>
            </td>
        </>
    );
}

function AddCardsPanel({ submission }: { submission: Props['submission'] }) {
    const [query, setQuery] = useState('');
    const [adding, setAdding] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [refresh, setRefresh] = useState(0);
    const { data, loading } = useLookup<{ items: CollectionItem[] }>(
        grading.candidates.url({
            query: { q: query || undefined, r: refresh || undefined },
        }),
    );

    function add(item: CollectionItem) {
        setAdding(item.id);
        setError(null);
        router.post(
            grading.items.store.url(submission.id),
            { collection_item_ids: [item.id] },
            {
                preserveScroll: true,
                onSuccess: () => setRefresh((value) => value + 1),
                onError: (errors) =>
                    setError(
                        Object.values(errors)[0] ??
                            'Couldn’t add that card. Try again.',
                    ),
                onFinish: () => setAdding(null),
            },
        );
    }

    return (
        <Panel title="Add cards">
            <Input
                type="search"
                placeholder="Search your raw cards, e.g. “2018 prizm ohtani”"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                aria-label="Search your raw cards"
            />
            {error && <p className="mt-2 text-sm text-destructive">{error}</p>}
            <div className="mt-3">
                {loading && !data ? (
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Spinner /> Searching…
                    </p>
                ) : data && data.items.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {query
                            ? 'No raw cards on hand match that search. Graded, sold, and already-submitted cards are left out.'
                            : 'All your raw cards on hand are already in a submission, or you haven’t added any raw cards yet.'}
                    </p>
                ) : (
                    <ul className="divide-y">
                        {data?.items.map((item) => (
                            <li
                                key={item.id}
                                className="flex items-center gap-3 py-2 text-sm"
                            >
                                <CardThumb item={item} />
                                <span className="min-w-0 flex-1 truncate">
                                    {item.title}
                                </span>
                                <span className="text-muted-foreground tabular">
                                    {money(item.estimated_value)}
                                </span>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={adding !== null}
                                    onClick={() => add(item)}
                                >
                                    <Plus /> Add
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </Panel>
    );
}

function ResultsPanel({
    submission,
    items,
    totals,
}: {
    submission: Props['submission'];
    items: SubmissionItem[];
    totals: Props['totals'];
}) {
    return (
        <Panel title="Record results">
            <Form
                action={grading.results.store.url(submission.id)}
                method="post"
                options={{ preserveScroll: true }}
                className="grid gap-4"
            >
                {({ errors, processing }) => (
                    <>
                        <p className="text-sm text-muted-foreground">
                            Enter the grade and cert number from each slab.
                            Leave the grade blank for a card that came back
                            ungraded. Saving marks this submission completed and
                            puts the cards back in your collection as graded.
                        </p>
                        {errors.results && (
                            <p className="text-sm text-destructive">
                                {errors.results}
                            </p>
                        )}
                        <div className="divide-y rounded-md border">
                            {items.map((entry) => (
                                <div
                                    key={entry.item.id}
                                    className="grid items-center gap-2 p-3 sm:grid-cols-[1fr_6rem_9rem]"
                                >
                                    <span className="truncate text-sm">
                                        {entry.item.title}
                                    </span>
                                    <Input
                                        name={`results[${entry.item.id}][grade]`}
                                        aria-label={`Grade for ${entry.item.title}`}
                                        placeholder={`${submission.grader} grade`}
                                    />
                                    <Input
                                        name={`results[${entry.item.id}][cert_number]`}
                                        aria-label={`Cert number for ${entry.item.title}`}
                                        placeholder="Cert number"
                                        inputMode="numeric"
                                    />
                                </div>
                            ))}
                        </div>
                        <Field
                            label="Returned on"
                            htmlFor="results_returned_on"
                            error={errors.returned_on}
                            className="max-w-48"
                        >
                            <Input
                                id="results_returned_on"
                                type="date"
                                name="returned_on"
                                defaultValue={
                                    submission.returned_on ??
                                    new Date().toISOString().slice(0, 10)
                                }
                            />
                        </Field>
                        <label className="flex items-start gap-2 text-sm">
                            <input
                                type="checkbox"
                                name="add_costs_to_basis"
                                value="1"
                                defaultChecked
                                className="mt-0.5 size-4 accent-[var(--primary)]"
                            />
                            <span>
                                Add grading costs to each card’s purchase price
                                <span className="block text-muted-foreground">
                                    Each card gets its own fee plus an even
                                    share of the {money(totals.shipping)}{' '}
                                    shipping, so profit on a later sale reflects
                                    what the slab really cost you. Uncheck to
                                    leave purchase prices as they are.
                                </span>
                            </span>
                        </label>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="justify-self-start"
                        >
                            Record results and complete
                        </Button>
                    </>
                )}
            </Form>
        </Panel>
    );
}
