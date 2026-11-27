import { Head, Link } from '@inertiajs/react';
import { Calculator, Plus } from 'lucide-react';
import { useState } from 'react';
import type { GradingProgressData } from '@/components/grading/grading-progress';
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

export type SubmissionSummary = GradingProgressData & {
    id: number;
    grader: string;
    service_level: string | null;
    reference: string | null;
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
                            <SubmissionTable submissions={open} open />
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
    open = false,
}: {
    submissions: SubmissionSummary[];
    open?: boolean;
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
                            {open ? 'Out for' : 'Turnaround'}
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
                                {open && (
                                    <TrackingLink submission={submission} />
                                )}
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
                                {days(
                                    open
                                        ? submission.days_out
                                        : submission.turnaround_days,
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function days(value: number | null): string {
    return value === null ? '—' : count(value, 'day');
}

/**
 * "Track" for the leg that's moving: the return shipment once the grader ships it back,
 * otherwise the package on its way to the grader.
 */
function TrackingLink({ submission }: { submission: SubmissionSummary }) {
    const shipment =
        submission.status === 'shipped'
            ? submission.return
            : submission.outbound;

    if (!shipment.tracking_url || submission.status === 'preparing') {
        return null;
    }

    return (
        <a
            href={shipment.tracking_url}
            target="_blank"
            rel="noreferrer"
            className="ml-2 text-xs text-primary hover:underline"
        >
            Track {shipment.carrier_label}
        </a>
    );
}
