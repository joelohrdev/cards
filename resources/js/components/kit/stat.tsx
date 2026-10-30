import { cn } from '@/lib/utils';

export function Stat({
    label,
    value,
    detail,
    tone,
    className,
}: {
    label: string;
    value: React.ReactNode;
    detail?: React.ReactNode;
    tone?: 'gain' | 'loss';
    className?: string;
}) {
    return (
        <div className={cn('min-w-0', className)}>
            <p className="text-sm text-muted-foreground">{label}</p>
            <p
                className={cn(
                    'font-display text-3xl leading-tight font-bold tabular',
                    tone === 'gain' && 'text-gain',
                    tone === 'loss' && 'text-loss',
                )}
            >
                {value}
            </p>
            {detail && (
                <p className="text-xs text-muted-foreground">{detail}</p>
            )}
        </div>
    );
}
