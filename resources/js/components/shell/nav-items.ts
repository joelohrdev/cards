import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Award,
    BookOpenCheck,
    Boxes,
    FileDown,
    FileUp,
    HandCoins,
    LifeBuoy,
    Share2,
    ShoppingBag,
    GalleryVerticalEnd,
    Gauge,
    Heart,
    PlusSquare,
    Rows3,
    Tags,
} from 'lucide-react';
import { dashboard, help } from '@/routes';
import collection from '@/routes/collection';
import exportsRoutes from '@/routes/exports';
import imports from '@/routes/imports';
import grading from '@/routes/grading';
import ebayListings from '@/routes/ebay/listings';
import locations from '@/routes/locations';
import sales from '@/routes/sales';
import sets from '@/routes/sets';
import shareLinks from '@/routes/share-links';
import tags from '@/routes/tags';
import wantList from '@/routes/want-list';

export type ShellNavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
};

export type ShellNavGroup = {
    title: string;
    items: ShellNavItem[];
};

/**
 * The binder-spine navigation. Each feature area owns one group.
 */
export const navGroups: ShellNavGroup[] = [
    {
        title: 'Collection',
        items: [
            { title: 'Overview', href: dashboard(), icon: Gauge },
            {
                title: 'All cards',
                href: collection.index(),
                icon: GalleryVerticalEnd,
            },
            {
                title: 'Add a card',
                href: collection.create(),
                icon: PlusSquare,
            },
            { title: 'Quick add', href: collection.quickAdd(), icon: Rows3 },
        ],
    },
    {
        title: 'Catalog',
        items: [
            {
                title: 'Sets & checklists',
                href: sets.index(),
                icon: BookOpenCheck,
            },
            { title: 'Want list', href: wantList.index(), icon: Heart },
        ],
    },
    {
        title: 'Organize',
        items: [
            { title: 'Tags', href: tags.index(), icon: Tags },
            { title: 'Storage', href: locations.index(), icon: Boxes },
        ],
    },
    {
        title: 'Selling',
        items: [
            { title: 'Grading', href: grading.index(), icon: Award },
            { title: 'Sales', href: sales.index(), icon: HandCoins },
            { title: 'eBay', href: ebayListings.index(), icon: ShoppingBag },
        ],
    },
    {
        title: 'Your data',
        items: [
            { title: 'Import', href: imports.index(), icon: FileUp },
            { title: 'Export', href: exportsRoutes.index(), icon: FileDown },
            { title: 'Share links', href: shareLinks.index(), icon: Share2 },
        ],
    },
    {
        title: 'Help',
        items: [{ title: 'How to use Cards', href: help(), icon: LifeBuoy }],
    },
];
