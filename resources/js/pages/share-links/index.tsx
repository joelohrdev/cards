import { Form, Head, router } from '@inertiajs/react';
import { ExternalLink, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { TagChip } from '@/components/cards/tag-chip';
import { CopyLinkButton } from '@/components/data/copy-link-button';
import { EmptyState } from '@/components/kit/empty-state';
import { Field, NativeSelect, Textarea } from '@/components/kit/field';
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
import { count, shortDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import shareLinks from '@/routes/share-links';
import type { TagSummary } from '@/types';

type ShareLink = {
    id: number;
    title: string;
    description: string | null;
    url: string;
    tag: TagSummary | null;
    show_values: boolean;
    expires_at: string | null;
    is_expired: boolean;
    cards_count: number;
    created_at: string;
};

type Props = {
    links: ShareLink[];
    tags: TagSummary[];
};

export default function ShareLinksIndex({ links, tags }: Props) {
    const [editing, setEditing] = useState<ShareLink | null>(null);
    const [deleting, setDeleting] = useState<ShareLink | null>(null);

    return (
        <>
            <Head title="Share links" />

            <PageHeader
                title="Share links"
                description="Show your cards to another collector with a read-only page. No account needed to view it, and you can turn a link off at any time."
            />

            <div className="grid gap-6 lg:grid-cols-[22rem_minmax(0,1fr)]">
                <Panel title="New share link" className="lg:self-start">
                    <Form
                        {...shareLinks.store.form()}
                        resetOnSuccess
                        className="space-y-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <ShareLinkFields errors={errors} tags={tags} />
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full"
                                >
                                    <Plus />{' '}
                                    {processing
                                        ? 'Creating…'
                                        : 'Create share link'}
                                </Button>
                            </>
                        )}
                    </Form>
                </Panel>

                <div className="min-w-0 space-y-3">
                    {links.length === 0 ? (
                        <EmptyState
                            title="No share links yet"
                            description="Create one to send a buyer, a trade partner or a friend a page of your cards. Tag the cards first if you only want to show some of them."
                        />
                    ) : (
                        links.map((link) => (
                            <article
                                key={link.id}
                                className={cn(
                                    'rounded-lg border bg-card p-4',
                                    link.is_expired && 'opacity-70',
                                )}
                            >
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="min-w-0">
                                        <h2 className="font-display text-xl leading-tight font-semibold">
                                            {link.title}
                                        </h2>
                                        <p className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                                            <span className="tabular">
                                                {count(
                                                    link.cards_count,
                                                    'card',
                                                )}
                                            </span>
                                            <span aria-hidden="true">·</span>
                                            {link.tag ? (
                                                <span className="inline-flex items-center gap-1">
                                                    Only{' '}
                                                    <TagChip
                                                        name={link.tag.name}
                                                        color={link.tag.color}
                                                    />
                                                </span>
                                            ) : (
                                                <span>Everything in hand</span>
                                            )}
                                            <span aria-hidden="true">·</span>
                                            <span>
                                                {link.show_values
                                                    ? 'Values shown'
                                                    : 'Values hidden'}
                                            </span>
                                            <span aria-hidden="true">·</span>
                                            <span
                                                className={cn(
                                                    link.is_expired &&
                                                        'font-medium text-loss',
                                                )}
                                            >
                                                {link.expires_at
                                                    ? `${link.is_expired ? 'Expired' : 'Expires'} ${shortDate(link.expires_at)}`
                                                    : 'Never expires'}
                                            </span>
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 flex-wrap gap-2">
                                        <CopyLinkButton url={link.url} />
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            asChild
                                        >
                                            <a
                                                href={link.url}
                                                target="_blank"
                                                rel="noreferrer"
                                                aria-label={`Open ${link.title}`}
                                            >
                                                <ExternalLink />
                                            </a>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => setEditing(link)}
                                            aria-label={`Edit ${link.title}`}
                                        >
                                            <Pencil />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="text-destructive hover:text-destructive"
                                            onClick={() => setDeleting(link)}
                                            aria-label={`Delete ${link.title}`}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                </div>
                                <input
                                    readOnly
                                    value={link.url}
                                    onFocus={(event) => event.target.select()}
                                    className="mt-3 w-full truncate rounded-md border bg-muted/50 px-3 py-1.5 font-mono text-xs text-muted-foreground"
                                    aria-label={`URL for ${link.title}`}
                                />
                            </article>
                        ))
                    )}

                    <p className="text-xs text-muted-foreground">
                        Visitors see photos, set, number, player, parallel,
                        serial number and grade. They never see what you paid,
                        your notes, cert numbers, tags or where cards are
                        stored.
                    </p>
                </div>
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit share link</DialogTitle>
                        <DialogDescription>
                            The URL stays the same, so anyone you’ve sent it to
                            sees the changes.
                        </DialogDescription>
                    </DialogHeader>
                    {editing && (
                        <Form
                            {...shareLinks.update.form(editing.id)}
                            onSuccess={() => setEditing(null)}
                            className="space-y-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <ShareLinkFields
                                        errors={errors}
                                        tags={tags}
                                        link={editing}
                                    />
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setEditing(null)}
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save changes
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete “{deleting?.title}”?</DialogTitle>
                        <DialogDescription>
                            Anyone who has the link will get a “not found” page.
                            Your cards aren’t affected.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleting(null)}
                        >
                            Keep it
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                deleting &&
                                router.delete(
                                    shareLinks.destroy.url(deleting.id),
                                    {
                                        onFinish: () => setDeleting(null),
                                    },
                                )
                            }
                        >
                            Delete link
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function ShareLinkFields({
    errors,
    tags,
    link,
}: {
    errors: Record<string, string | undefined>;
    tags: TagSummary[];
    link?: ShareLink;
}) {
    const prefix = link ? `edit-${link.id}` : 'new';

    return (
        <>
            <Field
                label="Title"
                htmlFor={`${prefix}-title`}
                error={errors.title}
            >
                <Input
                    id={`${prefix}-title`}
                    name="title"
                    defaultValue={link?.title}
                    placeholder="e.g. Griffey PC"
                    required
                />
            </Field>

            <Field
                label="Description"
                htmlFor={`${prefix}-description`}
                error={errors.description}
                hint="Optional. Shown at the top of the page."
            >
                <Textarea
                    id={`${prefix}-description`}
                    name="description"
                    defaultValue={link?.description ?? ''}
                    className="min-h-20"
                />
            </Field>

            <Field
                label="Which cards"
                htmlFor={`${prefix}-tag`}
                error={errors.tag_id}
                hint="Only cards still in hand are shown. Pick a tag to show just those cards."
            >
                <NativeSelect
                    id={`${prefix}-tag`}
                    name="tag_id"
                    defaultValue={link?.tag?.id ?? ''}
                >
                    <option value="">Everything in hand</option>
                    {tags.map((tag) => (
                        <option key={tag.id} value={tag.id}>
                            Only “{tag.name}”
                        </option>
                    ))}
                </NativeSelect>
            </Field>

            <Field
                label="Expires"
                htmlFor={`${prefix}-expires`}
                error={errors.expires_at}
                hint="Optional. Leave blank to keep it until you delete it."
            >
                <Input
                    id={`${prefix}-expires`}
                    name="expires_at"
                    type="date"
                    defaultValue={link?.expires_at ?? ''}
                />
            </Field>

            <label className="flex items-start gap-3 rounded-md border p-3 text-sm">
                <input
                    type="checkbox"
                    name="show_values"
                    value="1"
                    defaultChecked={link?.show_values ?? false}
                    className="mt-0.5 size-4 accent-[var(--primary)]"
                />
                <span>
                    <span className="font-medium">Show estimated values</span>
                    <span className="block text-muted-foreground">
                        Prices you paid are never shown.
                    </span>
                </span>
            </label>
        </>
    );
}
