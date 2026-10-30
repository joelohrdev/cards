import { cn } from '@/lib/utils';
import type { CardSummary } from '@/types';

/**
 * Compact attribute markers: RC, AUTO, RELIC, SP and print run.
 */
export function CardFlags({
    card,
    printRun,
    serialNumber,
    className,
}: {
    card: Pick<
        CardSummary,
        'is_rookie' | 'is_autograph' | 'is_memorabilia' | 'is_short_print'
    >;
    printRun?: number | null;
    serialNumber?: number | null;
    className?: string;
}) {
    const flags: { label: string; tone: 'ink' | 'foil' }[] = [];

    if (card.is_rookie) {
        flags.push({ label: 'RC', tone: 'ink' });
    }

    if (card.is_autograph) {
        flags.push({ label: 'Auto', tone: 'foil' });
    }

    if (card.is_memorabilia) {
        flags.push({ label: 'Relic', tone: 'ink' });
    }

    if (card.is_short_print) {
        flags.push({ label: 'SP', tone: 'ink' });
    }

    if (printRun) {
        flags.push({
            label: serialNumber
                ? `${serialNumber}/${printRun}`
                : `/${printRun}`,
            tone: 'foil',
        });
    }

    if (flags.length === 0) {
        return null;
    }

    return (
        <span className={cn('inline-flex flex-wrap gap-1', className)}>
            {flags.map((flag) => (
                <span
                    key={flag.label}
                    className={cn(
                        'rounded-sm px-1 py-0.5 font-display text-[0.7rem] leading-none font-bold tabular',
                        flag.tone === 'foil'
                            ? 'bg-foil text-foil-foreground'
                            : 'bg-foreground text-background',
                    )}
                >
                    {flag.label}
                </span>
            ))}
        </span>
    );
}
