import { cn } from '@/lib/utils';

/**
 * A grading submission's status: muted while preparing, slab red while out, gain green when done.
 */
export function StatusPill({
    status,
    label,
}: {
    status: string;
    label: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                status === 'completed' && 'bg-gain/10 text-gain',
                status === 'preparing' && 'bg-muted text-muted-foreground',
                !['completed', 'preparing'].includes(status) &&
                    'bg-slab/10 text-slab',
            )}
        >
            {label}
        </span>
    );
}
