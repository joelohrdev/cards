import { Head } from '@inertiajs/react';
import { ItemForm } from '@/components/collection/item-form';
import type { PickedCard } from '@/components/collection/card-picker';
import { PageHeader } from '@/components/kit/page-header';
import type { FormOptions } from '@/types';

export default function CollectionCreate({
    options,
    prefillCard,
}: {
    options: FormOptions;
    prefillCard: PickedCard | null;
}) {
    return (
        <>
            <Head title="Add a card" />
            <PageHeader title="Add a card" />
            <ItemForm options={options} prefillCard={prefillCard} />
        </>
    );
}
