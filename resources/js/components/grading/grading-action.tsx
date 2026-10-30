import { router } from '@inertiajs/react';
import { Award } from 'lucide-react';
import { useState } from 'react';
import { Field, NativeSelect } from '@/components/kit/field';
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
import { count, shortDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import grading from '@/routes/grading';
import type { CollectionItem, FormOptions } from '@/types';

type PreparingSubmission = {
    id: number;
    grader: string;
    service_level: string | null;
    reference: string | null;
    items_count: number;
    created_at: string;
};

/**
 * "Add to grading" for a raw card in hand: drop it into a submission that's still being
 * prepared, or start a new one.
 */
export function GradingAction({
    item,
    options,
}: {
    item: CollectionItem;
    options: FormOptions;
}) {
    const [open, setOpen] = useState(false);
    const [choice, setChoice] = useState<number | 'new' | null>(null);
    const [grader, setGrader] = useState('PSA');
    const [serviceLevel, setServiceLevel] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const { data, loading } = useLookup<{ submissions: PreparingSubmission[] }>(
        open ? grading.preparing.url() : null,
        0,
    );

    if (item.is_graded || item.status !== 'owned') {
        return null;
    }

    const submissions = data?.submissions ?? [];
    const selected = choice ?? submissions[0]?.id ?? 'new';

    function submit() {
        setProcessing(true);
        setError(null);

        const visitOptions = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onError: (errors: Record<string, string>) =>
                setError(
                    Object.values(errors)[0] ??
                        'Couldn’t add the card. Try again.',
                ),
            onFinish: () => setProcessing(false),
        };

        if (selected === 'new') {
            router.post(
                grading.store.url(),
                {
                    grader,
                    service_level: serviceLevel || null,
                    collection_item_ids: [item.id],
                },
                visitOptions,
            );
        } else {
            router.post(
                grading.items.store.url(selected),
                { collection_item_ids: [item.id] },
                visitOptions,
            );
        }
    }

    return (
        <>
            <Button
                variant="outline"
                onClick={() => {
                    setChoice(null);
                    setError(null);
                    setOpen(true);
                }}
            >
                <Award /> Add to grading
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Add to a grading submission</DialogTitle>
                        <DialogDescription>
                            Group the cards you’re sending together. They stay
                            in your collection until you mark the submission as
                            sent.
                        </DialogDescription>
                    </DialogHeader>

                    {loading && !data ? (
                        <p className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Spinner /> Loading your submissions…
                        </p>
                    ) : (
                        <fieldset className="grid gap-2">
                            <legend className="sr-only">Submission</legend>
                            {submissions.map((submission) => (
                                <ChoiceRow
                                    key={submission.id}
                                    checked={selected === submission.id}
                                    onSelect={() => setChoice(submission.id)}
                                    title={[
                                        submission.grader,
                                        submission.service_level,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                    detail={`${count(submission.items_count, 'card')} · started ${shortDate(submission.created_at)}${submission.reference ? ` · #${submission.reference}` : ''}`}
                                />
                            ))}
                            <ChoiceRow
                                checked={selected === 'new'}
                                onSelect={() => setChoice('new')}
                                title="Start a new submission"
                                detail={
                                    submissions.length === 0
                                        ? 'You have no submissions being prepared.'
                                        : undefined
                                }
                            />
                            {selected === 'new' && (
                                <div className="grid grid-cols-2 gap-3 rounded-md bg-muted/50 p-3">
                                    <Field label="Grader" htmlFor="new_grader">
                                        <NativeSelect
                                            id="new_grader"
                                            value={grader}
                                            onChange={(event) =>
                                                setGrader(event.target.value)
                                            }
                                        >
                                            {options.graders.map((option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </Field>
                                    <Field
                                        label="Service level"
                                        htmlFor="new_service_level"
                                    >
                                        <Input
                                            id="new_service_level"
                                            placeholder="e.g. Value"
                                            value={serviceLevel}
                                            onChange={(event) =>
                                                setServiceLevel(
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                </div>
                            )}
                        </fieldset>
                    )}

                    {error && (
                        <p className="text-sm text-destructive">{error}</p>
                    )}

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={submit}
                            disabled={processing || (loading && !data)}
                        >
                            {selected === 'new'
                                ? 'Start submission'
                                : 'Add card'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function ChoiceRow({
    checked,
    onSelect,
    title,
    detail,
}: {
    checked: boolean;
    onSelect: () => void;
    title: string;
    detail?: string;
}) {
    return (
        <label
            className={cn(
                'flex cursor-pointer items-start gap-3 rounded-md border px-3 py-2.5 text-sm',
                checked ? 'border-primary bg-primary/5' : 'hover:bg-muted/50',
            )}
        >
            <input
                type="radio"
                checked={checked}
                onChange={onSelect}
                className="mt-0.5 size-4 accent-[var(--primary)]"
            />
            <span>
                <span className="block font-medium">{title}</span>
                {detail && (
                    <span className="block text-muted-foreground">
                        {detail}
                    </span>
                )}
            </span>
        </label>
    );
}
