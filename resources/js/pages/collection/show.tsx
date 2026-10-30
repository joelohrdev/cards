import { Form, Head, Link, router } from '@inertiajs/react';
import { Camera, ExternalLink, Pencil, Star, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { CardArt } from '@/components/cards/card-art';
import { CardFlags } from '@/components/cards/card-flags';
import { CardTile } from '@/components/cards/card-tile';
import { ItemActions } from '@/components/collection/item-actions';
import { GradeBadge } from '@/components/cards/grade-badge';
import { TagChip } from '@/components/cards/tag-chip';
import { Field, MoneyInput, NativeSelect } from '@/components/kit/field';
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
import comps from '@/routes/comps';
import locations from '@/routes/locations';
import sets from '@/routes/sets';
import type { CollectionItem, FormOptions, ParallelSummary } from '@/types';

type Comp = {
    id: number;
    price: number;
    sold_on: string;
    grader: string | null;
    grade: string | null;
    parallel: string | null;
    source: string | null;
    url: string | null;
    notes: string | null;
    matches_item: boolean;
};

type Props = {
    item: CollectionItem & { images: NonNullable<CollectionItem['images']> };
    parallels: ParallelSummary[];
    otherCopies: CollectionItem[];
    comps: Comp[];
    sale: {
        id: number;
        platform: string;
        sold_on: string;
        sale_price: number;
        fees: number;
        shipping_cost: number;
        net: number;
        buyer: string | null;
    } | null;
    listings: {
        id: number;
        title: string;
        price: number;
        status: string;
        url: string | null;
        published_at: string | null;
        error: string | null;
    }[];
    gradingSubmissions: {
        id: number;
        grader: string;
        status: string;
        submitted_on: string | null;
        resulting_grade: string | null;
    }[];
    options: FormOptions;
};

export default function CollectionShow({
    item,
    parallels,
    otherCopies,
    comps: compList,
    sale,
    listings,
    gradingSubmissions,
    options,
}: Props) {
    const [activeImage, setActiveImage] = useState(0);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const fileInput = useRef<HTMLInputElement>(null);
    const image = item.images[activeImage] ?? null;
    const gain =
        item.estimated_value !== null && item.purchase_price !== null
            ? item.estimated_value - item.purchase_price
            : null;

    function upload(files: FileList | null) {
        if (!files?.length) {
            return;
        }

        router.post(
            collection.images.store.url(item.id),
            { images: Array.from(files) },
            { forceFormData: true, preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={item.card.name} />

            <PageHeader
                eyebrow={
                    <Link
                        href={sets.show(item.card.set.id)}
                        className="hover:text-foreground"
                    >
                        {item.card.set.full_name}
                    </Link>
                }
                title={
                    <span>
                        <span className="text-muted-foreground">
                            #{item.card.number}
                        </span>{' '}
                        {item.card.name}
                    </span>
                }
                description={
                    <span className="flex flex-wrap items-center gap-2">
                        {item.parallel && (
                            <span className="font-medium text-foreground">
                                {item.parallel.name}
                            </span>
                        )}
                        <CardFlags
                            card={item.card}
                            printRun={item.print_run}
                            serialNumber={item.serial_number}
                        />
                        {item.card.team && <span>{item.card.team}</span>}
                        {item.card.subset && <span>· {item.card.subset}</span>}
                    </span>
                }
                actions={
                    <>
                        <ItemActions item={item} options={options} />
                        <Button asChild variant="outline">
                            <Link href={collection.edit(item.id)}>
                                <Pencil /> Edit
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmDelete(true)}
                        >
                            <Trash2 /> Delete
                        </Button>
                    </>
                }
            />

            <div className="grid gap-8 lg:grid-cols-[minmax(16rem,22rem)_1fr]">
                <div className="space-y-3">
                    <div className="relative">
                        <CardArt
                            item={{ ...item, image: image?.url ?? null }}
                            size="lg"
                        />
                        <GradeBadge
                            item={item}
                            className="absolute top-3 left-3 origin-top-left scale-125"
                        />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {item.images.map((entry, index) => (
                            <div key={entry.id} className="group relative">
                                <button
                                    type="button"
                                    onClick={() => setActiveImage(index)}
                                    className={cn(
                                        'block w-14 overflow-hidden rounded border-2',
                                        index === activeImage
                                            ? 'border-primary'
                                            : 'border-transparent',
                                    )}
                                    aria-label={`Show ${entry.side} photo`}
                                >
                                    <img
                                        src={entry.url}
                                        alt=""
                                        className="aspect-[5/7] w-full object-cover"
                                    />
                                </button>
                                <div className="absolute -top-2 -right-2 hidden gap-0.5 group-hover:flex">
                                    {index > 0 && (
                                        <button
                                            type="button"
                                            title="Make primary photo"
                                            className="rounded-full bg-card p-1 shadow ring-1 ring-border"
                                            onClick={() =>
                                                router.patch(
                                                    collection.images.update.url(
                                                        {
                                                            item: item.id,
                                                            image: entry.id,
                                                        },
                                                    ),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Star className="size-3" />
                                        </button>
                                    )}
                                    <button
                                        type="button"
                                        title="Delete photo"
                                        className="rounded-full bg-card p-1 shadow ring-1 ring-border"
                                        onClick={() =>
                                            router.delete(
                                                collection.images.destroy.url({
                                                    item: item.id,
                                                    image: entry.id,
                                                }),
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Trash2 className="size-3" />
                                    </button>
                                </div>
                            </div>
                        ))}
                        <button
                            type="button"
                            onClick={() => fileInput.current?.click()}
                            className="flex aspect-[5/7] w-14 flex-col items-center justify-center gap-1 rounded border-2 border-dashed text-xs text-muted-foreground hover:border-primary hover:text-primary"
                        >
                            <Camera className="size-4" />
                            Add
                        </button>
                        <input
                            ref={fileInput}
                            type="file"
                            accept="image/*"
                            capture="environment"
                            multiple
                            className="hidden"
                            onChange={(event) => upload(event.target.files)}
                        />
                    </div>
                    <p className="text-xs text-muted-foreground">
                        The first photo is the front. Add the back second.
                    </p>
                </div>

                <div className="space-y-6">
                    <div className="grid grid-cols-2 gap-px overflow-hidden rounded-lg border bg-border sm:grid-cols-4">
                        {[
                            [
                                'Worth',
                                money(item.estimated_value),
                                item.value_updated_at
                                    ? `Updated ${shortDate(item.value_updated_at)}`
                                    : 'No value yet',
                            ],
                            [
                                'Paid',
                                money(item.purchase_price),
                                item.purchase_date
                                    ? shortDate(item.purchase_date)
                                    : (item.purchase_source ?? ''),
                            ],
                            [
                                'Gain',
                                gain === null
                                    ? '—'
                                    : `${gain >= 0 ? '+' : '−'}${money(Math.abs(gain))}`,
                                '',
                            ],
                            [
                                'Status',
                                item.status_label,
                                item.condition_label ?? '',
                            ],
                        ].map(([label, value, detail]) => (
                            <div key={label} className="bg-card p-4">
                                <p className="text-sm text-muted-foreground">
                                    {label}
                                </p>
                                <p
                                    className={cn(
                                        'font-display text-2xl font-bold tabular',
                                        label === 'Gain' &&
                                            gain !== null &&
                                            (gain >= 0
                                                ? 'text-gain'
                                                : 'text-loss'),
                                    )}
                                >
                                    {value}
                                </p>
                                {detail && (
                                    <p className="truncate text-xs text-muted-foreground">
                                        {detail}
                                    </p>
                                )}
                            </div>
                        ))}
                    </div>

                    <Panel title="Details">
                        <dl className="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                            <Detail label="Set">
                                <Link
                                    href={sets.show(item.card.set.id)}
                                    className="text-primary hover:underline"
                                >
                                    {item.card.set.full_name}
                                </Link>
                            </Detail>
                            <Detail label="Sport">
                                {item.card.set.sport_label}
                            </Detail>
                            <Detail label="Players">
                                {item.card.players
                                    ?.map((player) => player.name)
                                    .join(', ') || '—'}
                            </Detail>
                            <Detail label="Condition">
                                {item.is_graded
                                    ? `${item.grader} ${item.grade}`
                                    : (item.condition_label ?? '—')}
                                {item.cert_number && (
                                    <span className="text-muted-foreground">
                                        {' '}
                                        · cert {item.cert_number}
                                    </span>
                                )}
                            </Detail>
                            <Detail label="Stored in">
                                {item.location ? (
                                    <Link
                                        href={locations.show(item.location.id)}
                                        className="text-primary hover:underline"
                                    >
                                        {item.location.path}
                                    </Link>
                                ) : (
                                    'Not placed yet'
                                )}
                                {item.storage_position &&
                                    ` · ${item.storage_position}`}
                            </Detail>
                            <Detail label="Added">
                                {shortDate(item.created_at)}
                            </Detail>
                            {item.tags && item.tags.length > 0 && (
                                <Detail label="Tags" className="sm:col-span-2">
                                    <div className="flex flex-wrap gap-1">
                                        {item.tags.map((tag) => (
                                            <Link
                                                key={tag.id}
                                                href={collection.index({
                                                    query: { tag: [tag.id] },
                                                })}
                                            >
                                                <TagChip
                                                    name={tag.name}
                                                    color={tag.color}
                                                />
                                            </Link>
                                        ))}
                                    </div>
                                </Detail>
                            )}
                            {item.notes && (
                                <Detail label="Notes" className="sm:col-span-2">
                                    <p className="whitespace-pre-line">
                                        {item.notes}
                                    </p>
                                </Detail>
                            )}
                        </dl>
                    </Panel>

                    <CompsPanel
                        item={item}
                        comps={compList}
                        parallels={parallels}
                        options={options}
                    />

                    {(sale ||
                        listings.length > 0 ||
                        gradingSubmissions.length > 0) && (
                        <Panel title="History">
                            <ul className="space-y-3 text-sm">
                                {sale && (
                                    <li className="flex flex-wrap justify-between gap-2">
                                        <span>
                                            Sold on {sale.platform} ·{' '}
                                            {shortDate(sale.sold_on)}
                                            {sale.buyer && ` to ${sale.buyer}`}
                                        </span>
                                        <span className="tabular">
                                            {money(sale.sale_price)} · net{' '}
                                            {money(sale.net)}
                                        </span>
                                    </li>
                                )}
                                {listings.map((listing) => (
                                    <li
                                        key={listing.id}
                                        className="flex flex-wrap justify-between gap-2"
                                    >
                                        <span>
                                            eBay listing ({listing.status})
                                            {listing.published_at &&
                                                ` · ${shortDate(listing.published_at)}`}
                                            {listing.error && (
                                                <span className="block text-destructive">
                                                    {listing.error}
                                                </span>
                                            )}
                                        </span>
                                        <span className="flex items-center gap-2 tabular">
                                            {money(listing.price)}
                                            {listing.url && (
                                                <a
                                                    href={listing.url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-primary"
                                                    aria-label="Open listing on eBay"
                                                >
                                                    <ExternalLink className="size-4" />
                                                </a>
                                            )}
                                        </span>
                                    </li>
                                ))}
                                {gradingSubmissions.map((submission) => (
                                    <li
                                        key={submission.id}
                                        className="flex flex-wrap justify-between gap-2"
                                    >
                                        <span>
                                            Sent to {submission.grader}
                                            {submission.submitted_on &&
                                                ` · ${shortDate(submission.submitted_on)}`}
                                        </span>
                                        <span>
                                            {submission.resulting_grade
                                                ? `Graded ${submission.resulting_grade}`
                                                : submission.status}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </Panel>
                    )}
                </div>
            </div>

            {otherCopies.length > 0 && (
                <section className="mt-10">
                    <h2 className="mb-3 font-display text-2xl font-bold">
                        Your other copies ({otherCopies.length})
                    </h2>
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                        {otherCopies.map((copy) => (
                            <CardTile key={copy.id} item={copy} />
                        ))}
                    </div>
                </section>
            )}

            <Dialog open={confirmDelete} onOpenChange={setConfirmDelete}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete this card?</DialogTitle>
                        <DialogDescription>
                            {item.title} will be removed with its photos and
                            history. If you sold or traded it, change its status
                            instead so it stays in your records.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmDelete(false)}
                        >
                            Keep it
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                router.delete(collection.destroy.url(item.id))
                            }
                        >
                            Delete card
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function Detail({
    label,
    children,
    className,
}: {
    label: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div className={className}>
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="mt-0.5">{children}</dd>
        </div>
    );
}

function CompsPanel({
    item,
    comps: compList,
    parallels,
    options,
}: {
    item: CollectionItem;
    comps: Comp[];
    parallels: ParallelSummary[];
    options: FormOptions;
}) {
    const [adding, setAdding] = useState(false);
    const matching = compList.filter((comp) => comp.matches_item);
    const average = matching.length
        ? Math.round(
              matching.reduce((sum, comp) => sum + comp.price, 0) /
                  matching.length,
          )
        : null;

    return (
        <Panel
            title="Sold comps"
            actions={
                <>
                    {average !== null && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    collection.comps.apply.url(item.id),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Use average ({money(average)})
                        </Button>
                    )}
                    <Button size="sm" onClick={() => setAdding(!adding)}>
                        {adding ? 'Cancel' : 'Add comp'}
                    </Button>
                </>
            }
        >
            {adding && (
                <Form
                    action={collection.comps.store.url(item.id)}
                    method="post"
                    options={{ preserveScroll: true }}
                    onSuccess={() => setAdding(false)}
                    className="mb-5 grid gap-3 rounded-lg bg-muted/50 p-3 sm:grid-cols-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <Field
                                label="Sold for"
                                error={errors.price}
                                className="sm:col-span-2"
                            >
                                <MoneyInput
                                    name="price"
                                    required
                                    placeholder="0.00"
                                />
                            </Field>
                            <Field
                                label="Date"
                                error={errors.sold_on}
                                className="sm:col-span-2"
                            >
                                <Input
                                    type="date"
                                    name="sold_on"
                                    required
                                    defaultValue={new Date()
                                        .toISOString()
                                        .slice(0, 10)}
                                />
                            </Field>
                            <Field label="Where" className="sm:col-span-2">
                                <Input name="source" defaultValue="eBay" />
                            </Field>
                            <Field label="Parallel" className="sm:col-span-2">
                                <NativeSelect
                                    name="parallel_id"
                                    defaultValue={
                                        item.parallel
                                            ? String(item.parallel.id)
                                            : ''
                                    }
                                >
                                    <option value="">Base</option>
                                    {parallels.map((parallel) => (
                                        <option
                                            key={parallel.id}
                                            value={parallel.id}
                                        >
                                            {parallel.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field label="Grader" className="sm:col-span-1">
                                <NativeSelect
                                    name="grader"
                                    defaultValue={item.grader ?? ''}
                                >
                                    <option value="">Raw</option>
                                    {options.graders.map((grader) => (
                                        <option
                                            key={grader.value}
                                            value={grader.value}
                                        >
                                            {grader.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field label="Grade" className="sm:col-span-1">
                                <Input
                                    name="grade"
                                    defaultValue={item.grade ?? ''}
                                />
                            </Field>
                            <Field
                                label="Listing link"
                                error={errors.url}
                                className="sm:col-span-2"
                            >
                                <Input
                                    name="url"
                                    type="url"
                                    placeholder="https://"
                                />
                            </Field>
                            <label className="flex items-center gap-2 text-sm sm:col-span-4">
                                <input
                                    type="checkbox"
                                    name="apply_to_item"
                                    value="1"
                                    className="size-4 accent-[var(--primary)]"
                                />
                                Also set this card’s value to this price
                            </label>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="sm:col-span-2"
                            >
                                Save comp
                            </Button>
                        </>
                    )}
                </Form>
            )}

            {compList.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Record what this card has sold for to keep its value honest.
                    Comps are shared across all your copies of this card.
                </p>
            ) : (
                <table className="w-full text-sm">
                    <thead className="text-left text-xs text-muted-foreground">
                        <tr>
                            <th className="pb-2 font-medium">Date</th>
                            <th className="pb-2 font-medium">Version</th>
                            <th className="pb-2 font-medium">Where</th>
                            <th className="pb-2 text-right font-medium">
                                Price
                            </th>
                            <th className="w-8" />
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {compList.map((comp) => (
                            <tr
                                key={comp.id}
                                className={cn(
                                    !comp.matches_item &&
                                        'text-muted-foreground',
                                )}
                            >
                                <td className="py-2">
                                    {shortDate(comp.sold_on)}
                                </td>
                                <td className="py-2">
                                    {[
                                        comp.parallel ?? 'Base',
                                        comp.grader
                                            ? `${comp.grader} ${comp.grade ?? ''}`
                                            : 'Raw',
                                    ].join(' · ')}
                                </td>
                                <td className="py-2">
                                    {comp.url ? (
                                        <a
                                            href={comp.url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="text-primary hover:underline"
                                        >
                                            {comp.source ?? 'Link'}
                                        </a>
                                    ) : (
                                        (comp.source ?? '—')
                                    )}
                                </td>
                                <td className="py-2 text-right font-medium tabular">
                                    {money(comp.price)}
                                </td>
                                <td className="py-2 text-right">
                                    <button
                                        type="button"
                                        className="text-muted-foreground hover:text-destructive"
                                        aria-label="Delete comp"
                                        onClick={() =>
                                            router.delete(
                                                comps.destroy.url(comp.id),
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Trash2 className="size-3.5" />
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
            {compList.length > 0 && (
                <p className="mt-3 text-xs text-muted-foreground">
                    Grayed comps don’t match this copy’s parallel and grade, so
                    they’re left out of the average.
                </p>
            )}
        </Panel>
    );
}
