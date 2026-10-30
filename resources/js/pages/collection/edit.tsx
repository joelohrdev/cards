import { Head, Link } from '@inertiajs/react';
import { ItemForm } from '@/components/collection/item-form';
import { PageHeader } from '@/components/kit/page-header';
import collection from '@/routes/collection';
import type { CollectionItem, FormOptions, ParallelSummary } from '@/types';

export default function CollectionEdit({
    item,
    parallels,
    options,
}: {
    item: CollectionItem;
    parallels: ParallelSummary[];
    options: FormOptions;
}) {
    return (
        <>
            <Head title={`Edit ${item.card.name}`} />
            <PageHeader
                eyebrow={
                    <Link
                        href={collection.show(item.id)}
                        className="hover:text-foreground"
                    >
                        ‹ Back to card
                    </Link>
                }
                title={`Edit ${item.card.name}`}
            />
            <ItemForm options={options} item={item} parallels={parallels} />
        </>
    );
}
