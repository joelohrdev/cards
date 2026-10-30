import { Form, Head, Link, router } from '@inertiajs/react';
import { Pencil, Printer, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { CardTile } from '@/components/cards/card-tile';
import { GradeBadge } from '@/components/cards/grade-badge';
import { EmptyState } from '@/components/kit/empty-state';
import { Field, NativeSelect, Textarea } from '@/components/kit/field';
import { PageHeader } from '@/components/kit/page-header';
import { locationIcons } from '@/components/organize/location-icons';
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
import { compactMoney, money } from '@/lib/format';
import locations from '@/routes/locations';
import type { CollectionItem, LocationOption, Option } from '@/types';

type Location = {
    id: number;
    name: string;
    path: string;
    type: string;
    code: string | null;
    notes: string | null;
    parent_id: number | null;
};

export default function LocationShow({
    location,
    children,
    items,
    types,
    locations: allLocations,
}: {
    location: Location;
    children: { id: number; name: string; type: string }[];
    items: CollectionItem[];
    types: Option[];
    locations: LocationOption[];
}) {
    const [editing, setEditing] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const total = items.reduce(
        (sum, item) => sum + (item.estimated_value ?? 0),
        0,
    );

    return (
        <>
            <Head title={location.name} />
            <PageHeader
                eyebrow={
                    <Link
                        href={locations.index()}
                        className="hover:text-foreground"
                    >
                        ‹ Storage
                    </Link>
                }
                title={
                    <span className="flex items-center gap-3">
                        {location.code && (
                            <span className="rounded-md border-2 border-foreground px-2 font-display text-2xl">
                                {location.code}
                            </span>
                        )}
                        {location.path}
                    </span>
                }
                description={`${items.length.toLocaleString()} ${items.length === 1 ? 'card' : 'cards'} · ${compactMoney(total)}${location.notes ? ` · ${location.notes}` : ''}`}
                actions={
                    <>
                        <Button
                            variant="outline"
                            onClick={() => window.print()}
                        >
                            <Printer /> Print inventory
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => setEditing(true)}
                        >
                            <Pencil /> Edit
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => setDeleting(true)}
                        >
                            <Trash2 /> Delete
                        </Button>
                    </>
                }
            />

            {children.length > 0 && (
                <div className="no-print mb-6 flex flex-wrap gap-2">
                    {children.map((child) => {
                        const Icon =
                            locationIcons[child.type] ?? locationIcons.other;

                        return (
                            <Link
                                key={child.id}
                                href={locations.show(child.id)}
                                className="flex items-center gap-2 rounded-lg border bg-card px-3 py-2 text-sm font-medium hover:bg-accent"
                            >
                                <Icon className="size-4 text-muted-foreground" />
                                {child.name}
                            </Link>
                        );
                    })}
                </div>
            )}

            {items.length === 0 ? (
                <EmptyState
                    title="Nothing stored here"
                    description="Select cards in the collection and choose “Move to storage” to put them here."
                />
            ) : (
                <>
                    <div className="no-print grid grid-cols-2 gap-1 sm:grid-cols-4 lg:grid-cols-6">
                        {items.map((item) => (
                            <CardTile key={item.id} item={item} />
                        ))}
                    </div>

                    <table className="hidden w-full text-sm print:table">
                        <thead>
                            <tr className="border-b text-left">
                                <th className="py-1">Spot</th>
                                <th className="py-1">Card</th>
                                <th className="py-1">Condition</th>
                                <th className="py-1 text-right">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item) => (
                                <tr key={item.id} className="border-b">
                                    <td className="py-1">
                                        {item.storage_position ?? ''}
                                    </td>
                                    <td className="py-1">{item.title}</td>
                                    <td className="py-1">
                                        <GradeBadge item={item} />
                                    </td>
                                    <td className="py-1 text-right tabular">
                                        {money(item.estimated_value)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </>
            )}

            <Dialog open={editing} onOpenChange={setEditing}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit {location.name}</DialogTitle>
                    </DialogHeader>
                    <Form
                        {...locations.update.form(location.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setEditing(false)}
                        className="grid gap-3"
                    >
                        {({ errors, processing }) => (
                            <>
                                <Field label="Name" error={errors.name}>
                                    <Input
                                        name="name"
                                        defaultValue={location.name}
                                    />
                                </Field>
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label="Type" error={errors.type}>
                                        <NativeSelect
                                            name="type"
                                            defaultValue={location.type}
                                        >
                                            {types.map((type) => (
                                                <option
                                                    key={type.value}
                                                    value={type.value}
                                                >
                                                    {type.label}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </Field>
                                    <Field
                                        label="Label code"
                                        error={errors.code}
                                    >
                                        <Input
                                            name="code"
                                            defaultValue={location.code ?? ''}
                                        />
                                    </Field>
                                </div>
                                <Field label="Inside" error={errors.parent_id}>
                                    <NativeSelect
                                        name="parent_id"
                                        defaultValue={location.parent_id ?? ''}
                                    >
                                        <option value="">
                                            Nothing (top level)
                                        </option>
                                        {allLocations
                                            .filter(
                                                (option) =>
                                                    option.id !== location.id,
                                            )
                                            .map((option) => (
                                                <option
                                                    key={option.id}
                                                    value={option.id}
                                                >
                                                    {option.path}
                                                </option>
                                            ))}
                                    </NativeSelect>
                                </Field>
                                <Field label="Notes" error={errors.notes}>
                                    <Textarea
                                        name="notes"
                                        defaultValue={location.notes ?? ''}
                                    />
                                </Field>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        Save storage
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog open={deleting} onOpenChange={setDeleting}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete {location.name}?</DialogTitle>
                        <DialogDescription>
                            Anything nested inside it is deleted too. The cards
                            stay in your collection, marked as not placed.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleting(false)}
                        >
                            Keep it
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                router.delete(
                                    locations.destroy.url(location.id),
                                )
                            }
                        >
                            Delete storage
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
