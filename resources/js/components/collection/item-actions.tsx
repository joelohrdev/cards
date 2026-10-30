import { EbayAction } from '@/components/ebay/ebay-action';
import { GradingAction } from '@/components/grading/grading-action';
import { SaleAction } from '@/components/selling/sale-action';
import type { CollectionItem, FormOptions } from '@/types';

/**
 * Feature-specific actions shown in the card page header.
 */
export function ItemActions({
    item,
    options,
}: {
    item: CollectionItem;
    options: FormOptions;
}) {
    return (
        <>
            <SaleAction item={item} options={options} />
            <GradingAction item={item} options={options} />
            <EbayAction item={item} options={options} />
        </>
    );
}
