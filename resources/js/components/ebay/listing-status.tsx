import { cn } from '@/lib/utils';

export type EbayListingStatus =
    'draft' | 'pending_approval' | 'active' | 'sold' | 'ended' | 'failed';

export const listingStatusLabels: Record<EbayListingStatus, string> = {
    active: 'Live',
    draft: 'Draft',
    pending_approval: 'Awaiting approval',
    sold: 'Sold',
    ended: 'Ended',
    failed: 'Failed',
};

const tones: Record<EbayListingStatus, string> = {
    active: 'bg-primary text-primary-foreground',
    draft: 'bg-muted text-muted-foreground',
    pending_approval: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    sold: 'bg-gain/15 text-gain',
    ended: 'border text-muted-foreground',
    failed: 'bg-destructive/10 text-destructive',
};

export function ListingStatusBadge({
    status,
    className,
}: {
    status: string;
    className?: string;
}) {
    const key = (status in tones ? status : 'draft') as EbayListingStatus;

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium',
                tones[key],
                className,
            )}
        >
            {listingStatusLabels[key]}
        </span>
    );
}
