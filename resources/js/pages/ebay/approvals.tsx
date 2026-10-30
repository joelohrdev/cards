import { Head, Link } from '@inertiajs/react';
import { CardThumb } from '@/components/cards/card-thumb';
import { ApprovalPanel } from '@/components/ebay/approval-panel';
import type { ListingApproval } from '@/components/ebay/approval-panel';
import { EmptyState } from '@/components/kit/empty-state';
import { PageHeader } from '@/components/kit/page-header';
import { Button } from '@/components/ui/button';
import { money } from '@/lib/format';
import collection from '@/routes/collection';
import ebay from '@/routes/ebay';
import type { CollectionItem } from '@/types';

type PendingListing = ListingApproval & {
    id: number;
    collection_item_id: number;
    title: string;
    price: number;
    item: CollectionItem;
};

export default function EbayApprovals({
    listings,
}: {
    listings: PendingListing[];
}) {
    const waitingOnYou = listings.filter((listing) => listing.can_decide);

    return (
        <>
            <Head title="Approvals" />

            <PageHeader
                title="Approvals"
                description={
                    waitingOnYou.length > 0
                        ? `${waitingOnYou.length} ${waitingOnYou.length === 1 ? 'listing is' : 'listings are'} waiting on you. A card goes to eBay once everyone else approves it.`
                        : 'A card goes to eBay once everyone other than the person listing it approves.'
                }
            />

            {listings.length === 0 ? (
                <EmptyState
                    title="Nothing waiting on approval"
                    description="When someone sends a listing for approval, it shows up here."
                    action={
                        <Button asChild variant="outline">
                            <Link href={ebay.listings.index()}>
                                See eBay listings
                            </Link>
                        </Button>
                    }
                />
            ) : (
                <ul className="space-y-4">
                    {listings.map((listing) => (
                        <li
                            key={listing.id}
                            className="flex flex-col gap-4 rounded-lg border bg-card p-4 sm:flex-row"
                        >
                            <Link
                                href={collection.show(
                                    listing.collection_item_id,
                                )}
                                className="shrink-0 self-start"
                            >
                                <CardThumb
                                    item={listing.item}
                                    className="w-20"
                                />
                            </Link>
                            <div className="min-w-0 flex-1 space-y-3">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <Link
                                            href={ebay.items.listing(
                                                listing.collection_item_id,
                                            )}
                                            className="block font-medium hover:underline"
                                        >
                                            {listing.title}
                                        </Link>
                                        <p className="text-xs text-muted-foreground">
                                            {listing.item.title}
                                        </p>
                                    </div>
                                    <p className="font-display text-2xl font-semibold tabular">
                                        {money(listing.price)}
                                    </p>
                                </div>
                                <ApprovalPanel
                                    listingId={listing.id}
                                    approval={listing}
                                    pending
                                />
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}
