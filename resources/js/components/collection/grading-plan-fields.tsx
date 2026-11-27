import { Field, NativeSelect } from '@/components/kit/field';
import { SuggestInput } from '@/components/kit/suggest-input';
import { Input } from '@/components/ui/input';
import { count } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Option } from '@/types';

export type OpenGradingSubmission = {
    id: number;
    grader: string;
    service_level: string | null;
    reference: string | null;
    status: string;
    status_label: string;
    is_sent: boolean;
    items_count: number;
};

export type GradingPlan = {
    plan: 'none' | 'going' | 'sent';
    submission_id: string;
    grader: string;
    service_level: string;
    status: string;
    submitted_on: string;
};

export const emptyGradingPlan: GradingPlan = {
    plan: 'none',
    submission_id: '',
    grader: 'PSA',
    service_level: '',
    status: 'submitted',
    submitted_on: '',
};

/**
 * What the server expects as `grading`, or null when the card isn't going to grading.
 */
export function gradingPayload(plan: GradingPlan) {
    if (plan.plan === 'none') {
        return null;
    }

    const joining = plan.submission_id !== '';

    return {
        plan: plan.plan,
        submission_id: joining ? Number(plan.submission_id) : null,
        grader: joining ? null : plan.grader,
        service_level: joining ? null : plan.service_level || null,
        status: !joining && plan.plan === 'sent' ? plan.status : null,
        submitted_on:
            !joining && plan.plan === 'sent' ? plan.submitted_on || null : null,
    };
}

const CHOICES: { value: GradingPlan['plan']; label: string }[] = [
    { value: 'none', label: 'Not sending' },
    { value: 'going', label: 'Going to send' },
    { value: 'sent', label: 'Already sent' },
];

/**
 * "Send for grading" on the Add a card form: mark a raw card as going to send, or as
 * already sent, either in an open submission or a new one.
 */
export function GradingPlanFields({
    plan,
    onChange,
    submissions,
    graders,
    statuses,
    errors,
}: {
    plan: GradingPlan;
    onChange: (plan: GradingPlan) => void;
    submissions: OpenGradingSubmission[];
    graders: Option[];
    statuses: Option[];
    errors: Record<string, string | undefined>;
}) {
    const matching = submissions.filter(
        (submission) => submission.is_sent === (plan.plan === 'sent'),
    );
    const update = (changes: Partial<GradingPlan>) =>
        onChange({ ...plan, ...changes });
    const startingNew = plan.submission_id === '';

    return (
        <div className="mt-5 border-t pt-4">
            <p className="mb-2 text-sm font-medium">Send for grading</p>
            <div className="mb-3 inline-flex flex-wrap rounded-lg border bg-muted p-1 text-sm">
                {CHOICES.map((choice) => (
                    <button
                        key={choice.value}
                        type="button"
                        onClick={() =>
                            update({ plan: choice.value, submission_id: '' })
                        }
                        className={cn(
                            'rounded-md px-3 py-1.5 font-medium text-muted-foreground',
                            plan.plan === choice.value &&
                                'bg-card text-foreground shadow-xs',
                        )}
                    >
                        {choice.label}
                    </button>
                ))}
            </div>

            {plan.plan !== 'none' && (
                <div className="grid gap-4 sm:grid-cols-2">
                    {matching.length > 0 && (
                        <Field
                            label="Submission"
                            error={errors['grading.submission_id']}
                            className="sm:col-span-2"
                        >
                            <NativeSelect
                                value={plan.submission_id}
                                onChange={(event) =>
                                    update({
                                        submission_id: event.target.value,
                                    })
                                }
                            >
                                <option value="">Start a new submission</option>
                                {matching.map((submission) => (
                                    <option
                                        key={submission.id}
                                        value={String(submission.id)}
                                    >
                                        {[
                                            submission.grader,
                                            submission.service_level,
                                            submission.reference &&
                                                `#${submission.reference}`,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}{' '}
                                        ({submission.status_label},{' '}
                                        {count(submission.items_count, 'card')})
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                    )}

                    {startingNew && (
                        <>
                            <Field
                                label="Grading company"
                                error={errors['grading.grader']}
                            >
                                <NativeSelect
                                    value={plan.grader}
                                    onChange={(event) =>
                                        update({ grader: event.target.value })
                                    }
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
                                error={errors['grading.service_level']}
                            >
                                <SuggestInput
                                    field="service_level"
                                    value={plan.service_level}
                                    placeholder="e.g. Value"
                                    onChange={(serviceLevel) =>
                                        update({ service_level: serviceLevel })
                                    }
                                />
                            </Field>
                        </>
                    )}

                    {startingNew && plan.plan === 'sent' && (
                        <>
                            <Field
                                label="Status"
                                error={errors['grading.status']}
                            >
                                <NativeSelect
                                    value={plan.status}
                                    onChange={(event) =>
                                        update({ status: event.target.value })
                                    }
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
                            <Field
                                label="Sent on"
                                error={errors['grading.submitted_on']}
                                hint="Leave blank for today."
                            >
                                <Input
                                    type="date"
                                    value={plan.submitted_on}
                                    onChange={(event) =>
                                        update({
                                            submitted_on: event.target.value,
                                        })
                                    }
                                />
                            </Field>
                        </>
                    )}

                    <p className="text-sm text-muted-foreground sm:col-span-2">
                        {plan.plan === 'going'
                            ? 'The card stays in your collection and shows up under “Going to send” on the Overview and Grading pages.'
                            : 'The card will show as “At grading”. Add tracking and fees on the submission’s page.'}
                    </p>
                </div>
            )}
        </div>
    );
}
