import { Form, Head, Link } from '@inertiajs/react';
import { Archive } from 'lucide-react';
import { locationIcons } from '@/components/organize/location-icons';
import { EmptyState } from '@/components/kit/empty-state';
import { Field, NativeSelect } from '@/components/kit/field';
import { PageHeader } from '@/components/kit/page-header';
import { Panel } from '@/components/kit/panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import collection from '@/routes/collection';
import locations from '@/routes/locations';
import type { LocationOption, Option } from '@/types';

export default function LocationsIndex({
    locations: tree,
    unplacedCount,
    types,
}: {
    locations: (LocationOption & { items_count: number })[];
    unplacedCount: number;
    types: Option[];
}) {
    return (
        <>
            <Head title="Storage" />
            <PageHeader
                title="Storage"
                description="Map your boxes, binders and cases so every card has an address. Nest rows or pages inside a box for exact spots."
            />

            <div className="grid gap-6 lg:grid-cols-[1fr_22rem]">
                <div>
                    {tree.length === 0 ? (
                        <EmptyState
                            title="No storage set up"
                            description="Add your first box or binder. You can nest rows, pages or slots inside it."
                        />
                    ) : (
                        <ul className="overflow-hidden rounded-lg border bg-card">
                            {tree.map((location) => {
                                const Icon =
                                    locationIcons[location.type] ?? Archive;

                                return (
                                    <li
                                        key={location.id}
                                        className="border-b last:border-0"
                                    >
                                        <Link
                                            href={locations.show(location.id)}
                                            className="flex items-center gap-3 py-2.5 pr-4 hover:bg-muted/40"
                                            style={{
                                                paddingLeft: `${1 + location.depth * 1.5}rem`,
                                            }}
                                        >
                                            <Icon className="size-4 text-muted-foreground" />
                                            <span className="flex-1 font-medium">
                                                {location.name}
                                            </span>
                                            <span className="text-sm text-muted-foreground tabular">
                                                {location.items_count.toLocaleString()}
                                            </span>
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                    {unplacedCount > 0 && (
                        <p className="mt-3 text-sm text-muted-foreground">
                            {unplacedCount.toLocaleString()}{' '}
                            {unplacedCount === 1
                                ? 'card isn’t'
                                : 'cards aren’t'}{' '}
                            placed yet.{' '}
                            <Link
                                href={collection.index({
                                    query: { location: 'none' },
                                })}
                                className="font-medium text-primary hover:underline"
                            >
                                Select them and move them in bulk
                            </Link>
                        </p>
                    )}
                </div>

                <Panel title="Add storage">
                    <Form
                        {...locations.store.form()}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="grid gap-3"
                    >
                        {({ errors, processing }) => (
                            <>
                                <Field label="Name" error={errors.name}>
                                    <Input
                                        name="name"
                                        placeholder="Box 4, Rookie binder…"
                                    />
                                </Field>
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label="Type" error={errors.type}>
                                        <NativeSelect
                                            name="type"
                                            defaultValue="box"
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
                                        hint="Printed on the box"
                                        error={errors.code}
                                    >
                                        <Input name="code" placeholder="B4" />
                                    </Field>
                                </div>
                                <Field label="Inside" error={errors.parent_id}>
                                    <NativeSelect
                                        name="parent_id"
                                        defaultValue=""
                                    >
                                        <option value="">
                                            Nothing (top level)
                                        </option>
                                        {tree.map((location) => (
                                            <option
                                                key={location.id}
                                                value={location.id}
                                            >
                                                {location.path}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </Field>
                                <Button type="submit" disabled={processing}>
                                    Add storage
                                </Button>
                            </>
                        )}
                    </Form>
                </Panel>
            </div>
        </>
    );
}
