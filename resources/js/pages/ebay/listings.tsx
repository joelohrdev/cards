import { Head, Link } from '@inertiajs/react';
import { ExternalLink, Settings } from 'lucide-react';
import { CardThumb } from '@/components/cards/card-thumb';
import {
    ListingStatusBadge,
    listingStatusLabels,
} from '@/components/ebay/listing-status';
import type { EbayListingStatus } from '@/components/ebay/listing-status';
import { EmptyState } from '@/components/kit/empty-state';
import { PageHeader } from '@/components/kit/page-header';
import { Pagination } from '@/components/kit/pagination';
import { Button } from '@/components/ui/button';
import { money, shortDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import collection from '@/routes/collection';
import ebay from '@/routes/ebay';
import type { CollectionItem, Paginated } from '@/types';

type Listing = {
    id: number;
    collection_item_id: number;
    sku: string;
    title: string;
    price: number;
    status: EbayListingStatus;
    error: string | null;
    url: string | null;
    published_at: string | null;
    ended_at: string | null;
    updated_at: string | null;
    item: CollectionItem;
};

type Props = {
    listings: Paginated<Listing>;
    status: EbayListingStatus | null;
    counts: Record<EbayListingStatus, number>;
    connected: boolean;
    ready: boolean;
};

const tabs: (EbayListingStatus | null)[] = [
    null,
    'active',
    'draft',
    'sold',
    'ended',
];

export default function EbayListings({
    listings,
    status,
    counts,
    connected,
    ready,
}: Props) {
    const total = Object.values(counts).reduce((sum, count) => sum + count, 0);

    return (
        <>
            <Head title="eBay listings" />

            <PageHeader
                title="eBay listings"
                description="Cards you’ve listed or drafted for eBay. Sales are recorded automatically every 15 minutes."
                actions={
                    <Button asChild variant="outline">
                        <Link href={ebay.settings()}>
                            <Settings /> eBay settings
                        </Link>
                    </Button>
                }
            />

            {!ready && (
                <div className="mb-6 rounded-lg border bg-card p-4 text-sm">
                    <p className="font-medium">
                        {connected
                            ? 'Finish setting up eBay'
                            : 'Connect eBay to start listing'}
                    </p>
                    <p className="text-muted-foreground">
                        {connected
                            ? 'Choose your shipping, payment and return policies and your item location before publishing.'
                            : 'Connect your eBay seller account, then choose your policies and item location.'}{' '}
                        <Link
                            href={ebay.settings()}
                            className="text-primary hover:underline"
                        >
                            Open eBay settings
                        </Link>
                    </p>
                </div>
            )}

            <nav
                className="mb-4 flex flex-wrap gap-1"
                aria-label="Filter by status"
            >
                {tabs.map((tab) => {
                    const active = tab === status;
                    const count = tab === null ? total : counts[tab];

                    return (
                        <Link
                            key={tab ?? 'all'}
                            href={ebay.listings.index({
                                query: tab ? { status: tab } : {},
                            })}
                            className={cn(
                                'flex h-9 items-center gap-2 rounded-md border bg-card px-3 text-sm hover:bg-accent',
                                active &&
                                    'border-primary bg-primary text-primary-foreground hover:bg-primary',
                            )}
                        >
                            {tab === null ? 'All' : listingStatusLabels[tab]}
                            <span
                                className={cn(
                                    'tabular',
                                    active
                                        ? 'opacity-80'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {count}
                            </span>
                        </Link>
                    );
                })}
            </nav>

            {listings.data.length === 0 ? (
                <EmptyState
                    title={
                        status
                            ? `No ${listingStatusLabels[status].toLowerCase()} listings`
                            : 'Nothing listed on eBay yet'
                    }
                    description="Open a card in your collection and choose “List on eBay” to create a listing."
                    action={
                        <Button asChild>
                            <Link href={collection.index()}>
                                Browse your collection
                            </Link>
                        </Button>
                    }
                />
            ) : (
                <div className="overflow-hidden rounded-lg border bg-card">
                    <ul className="divide-y">
                        {listings.data.map((listing) => (
                            <li
                                key={listing.id}
                                className="flex items-center gap-4 p-3"
                            >
                                <Link
                                    href={collection.show(
                                        listing.collection_item_id,
                                    )}
                                    className="shrink-0"
                                >
                                    <CardThumb item={listing.item} />
                                </Link>
                                <div className="min-w-0 flex-1">
                                    <Link
                                        href={ebay.items.listing(
                                            listing.collection_item_id,
                                        )}
                                        className="block truncate font-medium hover:underline"
                                    >
                                        {listing.title}
                                    </Link>
                                    <p className="truncate text-xs text-muted-foreground">
                                        <Link
                                            href={collection.show(
                                                listing.collection_item_id,
                                            )}
                                            className="hover:text-foreground"
                                        >
                                            {listing.item.title}
                                        </Link>
                                        {' · '}
                                        {listing.status === 'active' &&
                                            listing.published_at &&
                                            `Listed ${shortDate(listing.published_at)}`}
                                        {(listing.status === 'ended' ||
                                            listing.status === 'sold') &&
                                            listing.ended_at &&
                                            `${listing.status === 'sold' ? 'Sold' : 'Ended'} ${shortDate(listing.ended_at)}`}
                                        {listing.status === 'draft' &&
                                            `Saved ${shortDate(listing.updated_at)}`}
                                    </p>
                                    {listing.error && (
                                        <p className="mt-1 line-clamp-2 text-xs text-destructive">
                                            {listing.error}
                                        </p>
                                    )}
                                </div>
                                <ListingStatusBadge status={listing.status} />
                                <p className="shrink-0 text-right font-display text-lg font-semibold tabular sm:w-24">
                                    {money(listing.price)}
                                </p>
                                <div className="hidden w-28 shrink-0 text-right sm:block">
                                    {listing.url &&
                                    listing.status !== 'draft' ? (
                                        <a
                                            href={listing.url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                                        >
                                            View on eBay{' '}
                                            <ExternalLink className="size-3.5" />
                                        </a>
                                    ) : (
                                        <Link
                                            href={ebay.items.listing(
                                                listing.collection_item_id,
                                            )}
                                            className="text-sm text-primary hover:underline"
                                        >
                                            {listing.status === 'draft'
                                                ? 'Finish listing'
                                                : 'Open'}
                                        </Link>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <Pagination page={listings} />
        </>
    );
}
