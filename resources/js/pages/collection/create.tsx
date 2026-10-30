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
            <PageHeader
                title="Add a card"
                description="Find the card in the shared catalog, or enter it new. Adding a big stack from one set? Quick add is faster."
            />
            <ItemForm options={options} prefillCard={prefillCard} />
        </>
    );
}
