import { Form, Head, Link, router } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { TagChip, tagDot } from '@/components/cards/tag-chip';
import { EmptyState } from '@/components/kit/empty-state';
import { PageHeader } from '@/components/kit/page-header';
import { Panel } from '@/components/kit/panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { compactMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import collection from '@/routes/collection';
import tagsRoutes from '@/routes/tags';

type TagRow = {
    id: number;
    name: string;
    color: string;
    items_count: number;
    total_value: number;
};

function ColorPicker({
    colors,
    defaultValue,
}: {
    colors: string[];
    defaultValue: string;
}) {
    const [color, setColor] = useState(defaultValue);

    return (
        <div
            className="flex flex-wrap gap-1.5"
            role="radiogroup"
            aria-label="Color"
        >
            <input type="hidden" name="color" value={color} />
            {colors.map((option) => (
                <button
                    key={option}
                    type="button"
                    role="radio"
                    aria-checked={color === option}
                    aria-label={option}
                    onClick={() => setColor(option)}
                    className={cn(
                        'size-6 rounded-full ring-offset-2 ring-offset-card',
                        tagDot[option],
                        color === option && 'ring-2 ring-foreground',
                    )}
                />
            ))}
        </div>
    );
}

export default function Tags({
    tags,
    colors,
}: {
    tags: TagRow[];
    colors: string[];
}) {
    const [editing, setEditing] = useState<number | null>(null);

    return (
        <>
            <Head title="Tags" />
            <PageHeader
                title="Tags"
                description="Tags are shared labels: PC, flip, grading candidates, show finds. Search for a tag’s name to find its cards."
            />

            <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
                <div>
                    {tags.length === 0 ? (
                        <EmptyState
                            title="No tags yet"
                            description="Create one here, or type a new tag on any card."
                        />
                    ) : (
                        <ul className="divide-y overflow-hidden rounded-lg border bg-card">
                            {tags.map((tag) =>
                                editing === tag.id ? (
                                    <li key={tag.id} className="p-3">
                                        <Form
                                            {...tagsRoutes.update.form(tag.id)}
                                            options={{ preserveScroll: true }}
                                            onSuccess={() => setEditing(null)}
                                            className="flex flex-wrap items-center gap-3"
                                        >
                                            {({ errors, processing }) => (
                                                <>
                                                    <Input
                                                        name="name"
                                                        defaultValue={tag.name}
                                                        className="w-56"
                                                        aria-label="Tag name"
                                                    />
                                                    <ColorPicker
                                                        colors={colors}
                                                        defaultValue={tag.color}
                                                    />
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        disabled={processing}
                                                    >
                                                        Save
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            setEditing(null)
                                                        }
                                                    >
                                                        Cancel
                                                    </Button>
                                                    {errors.name && (
                                                        <p className="w-full text-sm text-destructive">
                                                            {errors.name}
                                                        </p>
                                                    )}
                                                </>
                                            )}
                                        </Form>
                                    </li>
                                ) : (
                                    <li
                                        key={tag.id}
                                        className="flex items-center gap-3 px-4 py-3"
                                    >
                                        <Link
                                            href={collection.index({
                                                query: { tag: [tag.id] },
                                            })}
                                            className="flex flex-1 items-center gap-3"
                                        >
                                            <TagChip
                                                name={tag.name}
                                                color={tag.color}
                                                className="text-sm"
                                            />
                                            <span className="text-sm text-muted-foreground tabular">
                                                {tag.items_count.toLocaleString()}{' '}
                                                {tag.items_count === 1
                                                    ? 'card'
                                                    : 'cards'}{' '}
                                                ·{' '}
                                                {compactMoney(tag.total_value)}
                                            </span>
                                        </Link>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-8"
                                            onClick={() => setEditing(tag.id)}
                                            aria-label={`Edit ${tag.name}`}
                                        >
                                            <Pencil className="size-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-8 hover:text-destructive"
                                            aria-label={`Delete ${tag.name}`}
                                            onClick={() =>
                                                router.delete(
                                                    tagsRoutes.destroy.url(
                                                        tag.id,
                                                    ),
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </li>
                                ),
                            )}
                        </ul>
                    )}
                </div>

                <Panel title="New tag">
                    <Form
                        {...tagsRoutes.store.form()}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="grid gap-3"
                    >
                        {({ errors, processing }) => (
                            <>
                                <Input
                                    name="name"
                                    placeholder="Grading candidates"
                                    aria-label="Tag name"
                                />
                                {errors.name && (
                                    <p className="text-sm text-destructive">
                                        {errors.name}
                                    </p>
                                )}
                                <ColorPicker
                                    colors={colors}
                                    defaultValue="blue"
                                />
                                <Button type="submit" disabled={processing}>
                                    Create tag
                                </Button>
                            </>
                        )}
                    </Form>
                </Panel>
            </div>
        </>
    );
}
