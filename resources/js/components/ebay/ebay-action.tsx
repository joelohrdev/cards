import { Link } from '@inertiajs/react';
import { ShoppingBag } from 'lucide-react';
import { Button } from '@/components/ui/button';
import ebay from '@/routes/ebay';
import type { CollectionItem, FormOptions } from '@/types';

/**
 * Opens the eBay listing page for this card: "List on eBay" for cards in hand, "eBay listing" once listed.
 */
export function EbayAction({
    item,
    options,
}: {
    item: CollectionItem;
    options: FormOptions;
}) {
    void options;

    if (!['owned', 'listed', 'at-grading'].includes(item.status)) {
        return null;
    }

    return (
        <Button asChild variant="outline">
            <Link href={ebay.items.listing(item.id)}>
                <ShoppingBag />{' '}
                {item.status === 'listed' ? 'eBay listing' : 'List on eBay'}
            </Link>
        </Button>
    );
}
