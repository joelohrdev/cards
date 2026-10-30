import { Head, Link } from '@inertiajs/react';
import { Calculator, Plus } from 'lucide-react';
import { useState } from 'react';
import { GradingRoi } from '@/components/grading/grading-roi';
import { StatusPill } from '@/components/grading/status-pill';
import { EmptyState } from '@/components/kit/empty-state';
import { PageHeader } from '@/components/kit/page-header';
import { Panel } from '@/components/kit/panel';
import { Stat } from '@/components/kit/stat';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { count, money, shortDate } from '@/lib/format';
import grading from '@/routes/grading';

export type SubmissionSummary = {
    id: number;
    grader: string;
    service_level: string | null;
    reference: string | null;
    status: string;
    status_label: string;
    submitted_on: string | null;
    returned_on: string | null;
    shipping_cost: number;
    items_count: number;
    fee_total: number | null;
    declared_total: number | null;
    turnaround_days: number | null;
    created_at: string;
};

type Props = {
    open: SubmissionSummary[];
    completed: SubmissionSummary[];
    stats: {
        open_count: number;
        completed_count: number;
        cards_out: number;
        total_fees: number;
        average_turnaround_days: number | null;
    };
};

export default function GradingIndex({ open, completed, stats }: Props) {
    const [roiOpen, setRoiOpen] = useState(false);
    const hasAny = open.length + completed.length > 0;

    return (
        <>
            <Head title="Grading" />

            <PageHeader
                title="Grading"
                description="Track the cards you send to PSA, BGS, SGC and others, from packing to slabs back home."
                actions={
                    <>
                        <Button
                            variant="outline"
                            onClick={() => setRoiOpen(true)}
                        >
                            <Calculator /> Is it worth grading?
                        </Button>
                        <Button asChild>
                            <Link href={grading.create()}>
                                <Plus /> New submission
                            </Link>
                        </Button>
                    </>
                }
            />

            {hasAny && (
                <div className="mb-8 grid grid-cols-2 gap-6 rounded-lg border bg-card p-5 sm:grid-cols-4">
                    <Stat
                        label="Open submissions"
                        value={stats.open_count.toLocaleString()}
                        detail={`${stats.completed_count.toLocaleString()} completed`}
                    />
                    <Stat
                        label="Cards at the grader"
                        value={stats.cards_out.toLocaleString()}
                    />
                    <Stat
                        label="Spent on grading"
                        value={money(stats.total_fees)}
                        detail="Fees plus shipping"
                    />
                    <Stat
                        label="Average turnaround"
                        value={
                            stats.average_turnaround_days === null
                                ? '—'
                                : count(stats.average_turnaround_days, 'day')
                        }
                        detail={
                            stats.average_turnaround_days === null
                                ? 'Shows once a submission comes back'
                                : 'Sent to returned'
                        }
                    />
                </div>
            )}

            {!hasAny ? (
                <EmptyState
                    title="No grading submissions yet"
                    description="Start a submission, add the raw cards you’re sending, and mark it sent when it ships. When the slabs come back, record the grades here."
                    action={
                        <Button asChild>
                            <Link href={grading.create()}>
                                <Plus /> New submission
                            </Link>
                        </Button>
                    }
                />
            ) : (
                <div className="space-y-8">
                    <Panel title={`Open (${open.length})`} bodyClassName="p-0">
                        {open.length === 0 ? (
                            <p className="p-4 text-sm text-muted-foreground">
                                Nothing out right now. Start a new submission
                                when you’re ready to send more cards.
                            </p>
                        ) : (
                            <SubmissionTable submissions={open} />
                        )}
                    </Panel>
                    {completed.length > 0 && (
                        <Panel
                            title={`Completed (${completed.length})`}
                            bodyClassName="p-0"
                        >
                            <SubmissionTable submissions={completed} />
                        </Panel>
                    )}
                </div>
            )}

            <Dialog open={roiOpen} onOpenChange={setRoiOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Is it worth grading?</DialogTitle>
                        <DialogDescription>
                            Enter recent sold prices for each grade and how
                            likely you think each grade is.
                        </DialogDescription>
                    </DialogHeader>
                    <GradingRoi />
                </DialogContent>
            </Dialog>
        </>
    );
}

function SubmissionTable({
    submissions,
}: {
    submissions: SubmissionSummary[];
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead className="text-left text-xs text-muted-foreground">
                    <tr className="border-b">
                        <th className="px-4 py-2 font-medium">Submission</th>
                        <th className="px-4 py-2 font-medium">Status</th>
                        <th className="px-4 py-2 font-medium">Sent</th>
                        <th className="px-4 py-2 text-right font-medium">
                            Cards
                        </th>
                        <th className="px-4 py-2 text-right font-medium">
                            Fees + shipping
                        </th>
                        <th className="px-4 py-2 text-right font-medium">
                            Turnaround
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {submissions.map((submission) => (
                        <tr key={submission.id} className="hover:bg-muted/40">
                            <td className="px-4 py-3">
                                <Link
                                    href={grading.show(submission.id)}
                                    className="font-medium text-primary hover:underline"
                                >
                                    {[
                                        submission.grader,
                                        submission.service_level,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </Link>
                                {submission.reference && (
                                    <span className="ml-2 text-muted-foreground">
                                        #{submission.reference}
                                    </span>
                                )}
                            </td>
                            <td className="px-4 py-3">
                                <StatusPill
                                    status={submission.status}
                                    label={submission.status_label}
                                />
                            </td>
                            <td className="px-4 py-3 text-muted-foreground">
                                {shortDate(submission.submitted_on)}
                            </td>
                            <td className="px-4 py-3 text-right tabular">
                                {submission.items_count}
                            </td>
                            <td className="px-4 py-3 text-right tabular">
                                {money(
                                    (submission.fee_total ?? 0) +
                                        submission.shipping_cost,
                                )}
                            </td>
                            <td className="px-4 py-3 text-right text-muted-foreground tabular">
                                {submission.turnaround_days === null
                                    ? '—'
                                    : count(submission.turnaround_days, 'day')}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
