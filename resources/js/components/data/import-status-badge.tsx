import { cn } from '@/lib/utils';
import type { ImportStatus } from './types';

const labels: Record<ImportStatus, string> = {
    pending: 'Needs mapping',
    processing: 'Importing',
    completed: 'Finished',
    failed: 'Stopped',
};

export function ImportStatusBadge({
    status,
    className,
}: {
    status: ImportStatus;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-sm px-1.5 py-0.5 text-xs font-medium',
                status === 'completed' && 'bg-gain/12 text-gain',
                status === 'failed' && 'bg-loss/12 text-loss',
                status === 'processing' && 'bg-primary/12 text-primary',
                status === 'pending' && 'bg-muted text-muted-foreground',
                className,
            )}
        >
            {status === 'processing' && (
                <span
                    className="size-1.5 animate-pulse rounded-full bg-current"
                    aria-hidden="true"
                />
            )}
            {labels[status]}
        </span>
    );
}
