import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, ExternalLink, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import { CardArt } from '@/components/cards/card-art';
import { GradeBadge } from '@/components/cards/grade-badge';
import { ListingStatusBadge } from '@/components/ebay/listing-status';
import type { EbayListingStatus } from '@/components/ebay/listing-status';
import { Field, MoneyInput, Textarea } from '@/components/kit/field';
import { PageHeader } from '@/components/kit/page-header';
import { Panel } from '@/components/kit/panel';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { money, shortDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import collection from '@/routes/collection';
import ebay from '@/routes/ebay';
import type { CollectionItem } from '@/types';

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
};

type Props = {
    item: CollectionItem;
    listing: Listing | null;
    defaults: { title: string; description: string; price: string };
    suggestedTitle: string;
    aspects: Record<string, string[]>;
    photos: string[];
    warnings: string[];
    canList: boolean;
    connected: boolean;
    ready: boolean;
    configured: boolean;
    categoryId: string;
};

const TITLE_LIMIT = 80;

export default function EbayListingPage({
    item,
    listing,
    defaults,
    suggestedTitle,
    aspects,
    photos,
    warnings,
    canList,
    connected,
    ready,
    configured,
    categoryId,
}: Props) {
    const { errors: pageErrors } = usePage().props as {
        errors: Record<string, string>;
    };
    const [confirmEnd, setConfirmEnd] = useState(false);
    const [ending, setEnding] = useState(false);
    const form = useForm({ ...defaults, publish: false });
    const { data, setData, errors, processing } = form;

    const status = listing?.status ?? null;
    const isLive = status === 'active';
    const isDraft = status === 'draft';
    const isClosed = status === 'sold' || (status === 'ended' && !canList);
    const editsExisting = isLive || isDraft;
    const canPublish = configured && connected && ready;

    function submit(publish: boolean) {
        form.transform((values) => ({ ...values, publish }));

        if (editsExisting && listing) {
            form.patch(ebay.listings.update.url(listing.id), {
                preserveScroll: true,
            });
        } else {
            form.post(ebay.items.listing.store.url(item.id), {
                preserveScroll: true,
            });
        }
    }

    function endListing() {
        if (!listing) {
            return;
        }

        router.post(
            ebay.listings.end.url(listing.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setEnding(true),
                onFinish: () => {
                    setEnding(false);
                    setConfirmEnd(false);
                },
            },
        );
    }

    const heading = isLive
        ? 'Edit eBay listing'
        : isDraft
          ? 'Finish eBay listing'
          : status === 'ended'
            ? 'Relist on eBay'
            : 'List on eBay';

    return (
        <>
            <Head title={heading} />

            <PageHeader
                eyebrow={
                    <Link
                        href={collection.show(item.id)}
                        className="hover:text-foreground"
                    >
                        {item.title}
                    </Link>
                }
                title={
                    <span className="flex flex-wrap items-center gap-3">
                        {heading}
                        {status && (
                            <ListingStatusBadge
                                status={status}
                                className="font-sans text-sm"
                            />
                        )}
                    </span>
                }
                description={
                    isLive && listing?.published_at
                        ? `Live since ${shortDate(listing.published_at)}. Changes you save here update the listing on eBay.`
                        : 'Check the title, description and price, then publish. Item specifics and condition come from the card’s details.'
                }
                actions={
                    <>
                        {listing?.url && status !== 'draft' && (
                            <Button asChild variant="outline">
                                <a
                                    href={listing.url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <ExternalLink /> View on eBay
                                </a>
                            </Button>
                        )}
                        {isLive && (
                            <Button
                                variant="outline"
                                onClick={() => setConfirmEnd(true)}
                            >
                                End listing
                            </Button>
                        )}
                    </>
                }
            />

            <div className="grid gap-8 lg:grid-cols-[minmax(14rem,18rem)_1fr]">
                <div className="space-y-3">
                    <div className="relative">
                        <CardArt
                            item={{ ...item, image: photos[0] ?? null }}
                            size="lg"
                        />
                        <GradeBadge
                            item={item}
                            className="absolute top-3 left-3 origin-top-left scale-125"
                        />
                    </div>
                    {photos.length > 1 && (
                        <div className="flex flex-wrap gap-2">
                            {photos.slice(1).map((url) => (
                                <img
                                    key={url}
                                    src={url}
                                    alt=""
                                    className="aspect-[5/7] w-14 rounded border object-cover"
                                />
                            ))}
                        </div>
                    )}
                    <p className="text-xs text-muted-foreground">
                        {photos.length === 0
                            ? 'No photos yet.'
                            : `${photos.length} ${photos.length === 1 ? 'photo' : 'photos'} will be sent to eBay.`}{' '}
                        <Link
                            href={collection.show(item.id)}
                            className="text-primary hover:underline"
                        >
                            Manage photos
                        </Link>
                    </p>
                </div>

                <div className="space-y-6">
                    {!configured && (
                        <Notice>
                            eBay isn’t set up yet.{' '}
                            <Link
                                href={ebay.settings()}
                                className="text-primary hover:underline"
                            >
                                Open eBay settings
                            </Link>{' '}
                            to see which keys to add. You can still save a
                            draft.
                        </Notice>
                    )}
                    {configured && !ready && (
                        <Notice>
                            {connected
                                ? 'Choose your listing policies and item location before publishing.'
                                : 'Connect your eBay account before publishing.'}{' '}
                            <Link
                                href={ebay.settings()}
                                className="text-primary hover:underline"
                            >
                                Open eBay settings
                            </Link>
                            . You can still save a draft.
                        </Notice>
                    )}
                    {listing?.error && (
                        <div
                            className="rounded-lg border border-destructive/40 bg-destructive/5 p-4 text-sm"
                            role="alert"
                        >
                            <p className="font-medium text-destructive">
                                eBay didn’t accept this listing
                            </p>
                            <p className="mt-1">{listing.error}</p>
                            <p className="mt-1 text-muted-foreground">
                                Fix the problem above, then choose “Publish to
                                eBay” again.
                            </p>
                        </div>
                    )}
                    {pageErrors.ebay && (
                        <div
                            className="rounded-lg border border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive"
                            role="alert"
                        >
                            {pageErrors.ebay}
                        </div>
                    )}
                    {!isClosed &&
                        warnings.map((warning) => (
                            <Notice key={warning}>{warning}</Notice>
                        ))}

                    {isClosed ? (
                        <Panel title="This listing is closed">
                            <p className="text-sm text-muted-foreground">
                                {status === 'sold'
                                    ? `Sold on eBay for ${money(listing?.price)}. The sale is recorded on the card.`
                                    : `This card is marked ${item.status_label.toLowerCase()}, so it can’t be relisted.`}
                            </p>
                        </Panel>
                    ) : !canList ? (
                        <Panel title="This card can’t be listed">
                            <p className="text-sm text-muted-foreground">
                                It’s marked {item.status_label.toLowerCase()}.
                                Only cards you still have can be listed on eBay.
                            </p>
                        </Panel>
                    ) : (
                        <Panel title="Listing">
                            <form
                                className="space-y-4"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    submit(!isLive && canPublish);
                                }}
                            >
                                <Field
                                    label="Title"
                                    htmlFor="title"
                                    error={errors.title}
                                    hint={
                                        <span className="flex justify-between gap-3">
                                            <span>
                                                Lead with year, set, player and
                                                grade — that’s what buyers
                                                search for.
                                            </span>
                                            <span
                                                className={cn(
                                                    'tabular',
                                                    data.title.length >
                                                        TITLE_LIMIT &&
                                                        'text-destructive',
                                                )}
                                            >
                                                {data.title.length}/
                                                {TITLE_LIMIT}
                                            </span>
                                        </span>
                                    }
                                >
                                    <div className="flex gap-2">
                                        <Input
                                            id="title"
                                            value={data.title}
                                            maxLength={TITLE_LIMIT}
                                            onChange={(event) =>
                                                setData(
                                                    'title',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={Boolean(errors.title)}
                                        />
                                        {data.title !== suggestedTitle && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                title="Use the suggested title"
                                                onClick={() =>
                                                    setData(
                                                        'title',
                                                        suggestedTitle,
                                                    )
                                                }
                                            >
                                                <RotateCcw />
                                            </Button>
                                        )}
                                    </div>
                                </Field>

                                <Field
                                    label="Price"
                                    htmlFor="price"
                                    error={errors.price}
                                    hint="Fixed price, good until cancelled."
                                    className="max-w-48"
                                >
                                    <MoneyInput
                                        id="price"
                                        value={data.price}
                                        onChange={(event) =>
                                            setData('price', event.target.value)
                                        }
                                        aria-invalid={Boolean(errors.price)}
                                    />
                                </Field>

                                <Field
                                    label="Description"
                                    htmlFor="description"
                                    error={errors.description}
                                    hint="Plain text. Line breaks are kept."
                                >
                                    <Textarea
                                        id="description"
                                        rows={10}
                                        value={data.description}
                                        onChange={(event) =>
                                            setData(
                                                'description',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <div className="flex flex-wrap gap-2 pt-2">
                                    {isLive ? (
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save changes to eBay
                                        </Button>
                                    ) : (
                                        <>
                                            <Button
                                                type="submit"
                                                disabled={
                                                    processing || !canPublish
                                                }
                                            >
                                                Publish to eBay
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled={processing}
                                                onClick={() => submit(false)}
                                            >
                                                Save draft
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </form>
                        </Panel>
                    )}

                    <Panel title="Item specifics">
                        <dl className="grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                            {Object.entries(aspects).map(([name, values]) => (
                                <div
                                    key={name}
                                    className="flex justify-between gap-4 border-b border-dashed pb-1.5"
                                >
                                    <dt className="text-muted-foreground">
                                        {name}
                                    </dt>
                                    <dd className="text-right">
                                        {values.join(', ')}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                        <p className="mt-3 text-xs text-muted-foreground">
                            Listed in Sports Trading Card Singles (category{' '}
                            {categoryId}) as{' '}
                            {item.is_graded ? 'graded' : 'ungraded'}. To change
                            these,{' '}
                            <Link
                                href={collection.edit(item.id)}
                                className="text-primary hover:underline"
                            >
                                edit the card
                            </Link>
                            .
                            {listing && (
                                <>
                                    {' '}
                                    SKU{' '}
                                    <span className="font-mono">
                                        {listing.sku}
                                    </span>
                                    .
                                </>
                            )}
                        </p>
                    </Panel>
                </div>
            </div>

            <Dialog open={confirmEnd} onOpenChange={setConfirmEnd}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>End this eBay listing?</DialogTitle>
                        <DialogDescription>
                            The listing comes down from eBay right away and the
                            card goes back to owned. You can relist it later.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmEnd(false)}
                        >
                            Keep it listed
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={ending}
                            onClick={endListing}
                        >
                            End listing
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function Notice({ children }: { children: React.ReactNode }) {
    return (
        <div className="flex gap-3 rounded-lg border bg-card p-4 text-sm">
            <AlertTriangle className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <p>{children}</p>
        </div>
    );
}
