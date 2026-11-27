import { Check, ExternalLink } from 'lucide-react';
import { count, shortDate } from '@/lib/format';
import { cn } from '@/lib/utils';

export type Shipment = {
    carrier: string | null;
    carrier_label: string | null;
    tracking: string | null;
    tracking_url: string | null;
};

/**
 * GradingSubmission::progress() on the server.
 */
export type GradingProgressData = {
    status: string;
    status_label: string;
    submitted_on: string | null;
    received_on: string | null;
    shipped_back_on: string | null;
    returned_on: string | null;
    outbound: Shipment;
    return: Shipment;
    days_out: number | null;
};

const MILESTONES: {
    status: string;
    label: string;
    date: (progress: GradingProgressData) => string | null;
    shipment?: (progress: GradingProgressData) => Shipment;
}[] = [
    {
        status: 'submitted',
        label: 'Sent to grader',
        date: (progress) => progress.submitted_on,
        shipment: (progress) => progress.outbound,
    },
    {
        status: 'received',
        label: 'Received by grader',
        date: (progress) => progress.received_on,
    },
    { status: 'grading', label: 'Grading', date: () => null },
    {
        status: 'shipped',
        label: 'Shipped back',
        date: (progress) => progress.shipped_back_on,
        shipment: (progress) => progress.return,
    },
    {
        status: 'completed',
        label: 'Back in hand',
        date: (progress) => progress.returned_on,
    },
];

const STATUS_ORDER = ['preparing', ...MILESTONES.map((step) => step.status)];

/**
 * Where a grading submission is: each milestone with its date and, for the two
 * shipments, the carrier and a tracking link.
 */
export function GradingProgress({
    progress,
}: {
    progress: GradingProgressData;
}) {
    const reached = STATUS_ORDER.indexOf(progress.status);

    return (
        <div>
            <ol className="grid gap-3">
                {MILESTONES.map((step, index) => {
                    const done = reached >= index + 1;
                    const current = reached === index + 1;
                    const date = step.date(progress);
                    const shipment = step.shipment?.(progress);

                    return (
                        <li key={step.status} className="flex gap-3">
                            <span
                                className={cn(
                                    'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border text-[10px]',
                                    done
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {done ? (
                                    <Check className="size-3" />
                                ) : (
                                    index + 1
                                )}
                            </span>
                            <div className="text-sm">
                                <p
                                    className={cn(
                                        current && 'font-medium',
                                        !done && 'text-muted-foreground',
                                    )}
                                >
                                    {step.label}
                                </p>
                                {(date || shipment?.tracking) && (
                                    <p className="text-xs text-muted-foreground">
                                        {date && shortDate(date)}
                                        {date && shipment?.tracking && ' · '}
                                        {shipment?.tracking && (
                                            <TrackingNumber
                                                shipment={shipment}
                                            />
                                        )}
                                    </p>
                                )}
                            </div>
                        </li>
                    );
                })}
            </ol>
            {progress.days_out !== null && (
                <p className="mt-4 text-sm text-muted-foreground">
                    Out for {count(progress.days_out, 'day')}.
                </p>
            )}
        </div>
    );
}

export function TrackingNumber({ shipment }: { shipment: Shipment }) {
    const text = [shipment.carrier_label, shipment.tracking]
        .filter(Boolean)
        .join(' ');

    if (!shipment.tracking_url) {
        return <span>{text}</span>;
    }

    return (
        <a
            href={shipment.tracking_url}
            target="_blank"
            rel="noreferrer"
            className="inline-flex items-center gap-1 text-primary hover:underline"
        >
            {text}
            <ExternalLink className="size-3" />
        </a>
    );
}
