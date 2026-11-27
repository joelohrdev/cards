import { Head } from '@inertiajs/react';
import { ItemForm } from '@/components/collection/item-form';
import type { PickedCard } from '@/components/collection/card-picker';
import type { OpenGradingSubmission } from '@/components/collection/grading-plan-fields';
import { PageHeader } from '@/components/kit/page-header';
import type { FormOptions, Option } from '@/types';

export default function CollectionCreate({
    options,
    prefillCard,
    gradingSubmissions,
    gradingStatuses,
}: {
    options: FormOptions;
    prefillCard: PickedCard | null;
    gradingSubmissions: OpenGradingSubmission[];
    gradingStatuses: Option[];
}) {
    return (
        <>
            <Head title="Add a card" />
            <PageHeader title="Add a card" />
            <ItemForm
                options={options}
                prefillCard={prefillCard}
                gradingSubmissions={gradingSubmissions}
                gradingStatuses={gradingStatuses}
            />
        </>
    );
}
