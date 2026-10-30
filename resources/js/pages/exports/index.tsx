import { Head, Link } from '@inertiajs/react';
import { Download, FileSpreadsheet, Printer, Share2 } from 'lucide-react';
import { PageHeader } from '@/components/kit/page-header';
import { Panel } from '@/components/kit/panel';
import { Button } from '@/components/ui/button';
import { count } from '@/lib/format';
import exportsRoutes from '@/routes/exports';
import imports from '@/routes/imports';
import shareLinks from '@/routes/share-links';

type Props = {
    counts: { all: number; in_hand: number };
};

export default function ExportsIndex({ counts }: Props) {
    return (
        <>
            <Head title="Export" />

            <PageHeader
                title="Export your collection"
                description="Keep a backup, hand your insurer an inventory, or move your data somewhere else. It’s your data."
            />

            <div className="grid gap-6 lg:grid-cols-2">
                <Panel title="Spreadsheet backup (CSV)">
                    <div className="flex h-full flex-col gap-4">
                        <FileSpreadsheet
                            className="size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="text-sm text-muted-foreground">
                            Every card you’ve recorded (
                            {count(counts.all, 'card')}, including sold and
                            traded ones) with all its details: set, number,
                            players, parallel, serial, grade and cert,
                            condition, status, price paid, value, purchase date
                            and source, storage location, tags and notes. Money
                            is in dollars.
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Opens in Excel, Numbers or Google Sheets, and{' '}
                            <Link
                                href={imports.index()}
                                className="font-medium text-primary hover:underline"
                            >
                                Import
                            </Link>{' '}
                            reads it straight back in. Photos aren’t included.
                        </p>
                        <div>
                            <Button asChild>
                                <a
                                    href={exportsRoutes.collection.url()}
                                    download
                                >
                                    <Download /> Download CSV
                                </a>
                            </Button>
                        </div>
                    </div>
                </Panel>

                <Panel title="Insurance inventory">
                    <div className="flex h-full flex-col gap-4">
                        <Printer
                            className="size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="text-sm text-muted-foreground">
                            A printable list of the{' '}
                            {count(counts.in_hand, 'card')} you still have, most
                            valuable first, with a photo, condition, cert
                            number, storage location, what you paid and what
                            each is worth, plus totals. Print it or save it as a
                            PDF from your browser.
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Cards without an estimated value are listed last.
                            Add values first for a complete report.
                        </p>
                        <div>
                            <Button asChild variant="outline">
                                <Link href={exportsRoutes.insurance()}>
                                    <Printer /> Open the inventory report
                                </Link>
                            </Button>
                        </div>
                    </div>
                </Panel>
            </div>

            <p className="mt-6 flex items-center gap-2 text-sm text-muted-foreground">
                <Share2 className="size-4" aria-hidden="true" />
                Want to show cards to someone rather than send a file?
                <Link
                    href={shareLinks.index()}
                    className="font-medium text-primary hover:underline"
                >
                    Make a share link
                </Link>
            </p>
        </>
    );
}
