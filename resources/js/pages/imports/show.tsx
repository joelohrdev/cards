import { Head, Link, router, usePage, usePoll } from '@inertiajs/react';
import { ArrowLeft, GalleryVerticalEnd, Trash2, Upload } from 'lucide-react';
import { useEffect, useState } from 'react';
import { TagChip } from '@/components/cards/tag-chip';
import { ImportStatusBadge } from '@/components/data/import-status-badge';
import { ProgressBar } from '@/components/data/progress-bar';
import type { ImportSummary } from '@/components/data/types';
import { PageHeader } from '@/components/kit/page-header';
import { Panel } from '@/components/kit/panel';
import { Stat } from '@/components/kit/stat';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { count, shortDate } from '@/lib/format';
import collection from '@/routes/collection';
import imports from '@/routes/imports';

type Props = {
    import: ImportSummary & {
        failures: { row: number; message: string }[];
        failures_truncated: boolean;
        tag_items_count: number;
    };
};

export default function ImportShow({ import: upload }: Props) {
    const running = upload.status === 'processing';
    const { errors } = usePage<{ errors: Record<string, string | undefined> }>()
        .props;
    const [confirmUndo, setConfirmUndo] = useState(false);
    const { start, stop } = usePoll(
        2000,
        { only: ['import'] },
        { autoStart: running },
    );

    useEffect(() => {
        if (running) {
            start();
        } else {
            stop();
        }
    }, [running, start, stop]);

    const percent =
        upload.total_rows > 0
            ? Math.round((upload.processed_rows / upload.total_rows) * 100)
            : 0;

    return (
        <>
            <Head title={`Import · ${upload.original_filename}`} />

            <PageHeader
                eyebrow={
                    <Link
                        href={imports.index()}
                        className="inline-flex items-center gap-1 hover:text-foreground"
                    >
                        <ArrowLeft className="size-3.5" /> Imports
                    </Link>
                }
                title={upload.original_filename}
                description={
                    <span className="inline-flex flex-wrap items-center gap-2">
                        <ImportStatusBadge status={upload.status} />
                        Uploaded {shortDate(upload.created_at)}
                        {upload.completed_at && (
                            <> · finished {shortDate(upload.completed_at)}</>
                        )}
                    </span>
                }
                actions={
                    <Button variant="outline" asChild>
                        <Link href={imports.index()}>
                            <Upload /> Import another file
                        </Link>
                    </Button>
                }
            />

            {errors.import && (
                <p className="mb-4 rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                    {errors.import}
                </p>
            )}

            <Panel>
                <div className="grid gap-6 sm:grid-cols-3">
                    <Stat
                        label="Rows read"
                        value={`${upload.processed_rows.toLocaleString()} / ${upload.total_rows.toLocaleString()}`}
                    />
                    <Stat
                        label="Imported"
                        value={upload.imported_rows.toLocaleString()}
                        tone={upload.imported_rows > 0 ? 'gain' : undefined}
                    />
                    <Stat
                        label="Skipped"
                        value={upload.failed_rows.toLocaleString()}
                        tone={upload.failed_rows > 0 ? 'loss' : undefined}
                        detail={
                            upload.failed_rows > 0
                                ? 'See the reasons below.'
                                : undefined
                        }
                    />
                </div>
                <ProgressBar
                    className="mt-5"
                    value={upload.processed_rows}
                    max={upload.total_rows}
                    label="Import progress"
                />
                <p className="mt-2 text-sm text-muted-foreground tabular">
                    {running
                        ? `${percent}% done. This page updates by itself; you can leave and come back.`
                        : upload.status === 'failed'
                          ? 'The import stopped unexpectedly. Cards imported before it stopped are kept.'
                          : 'All rows have been read.'}
                </p>
            </Panel>

            {upload.tag && (
                <Panel title="Imported cards" className="mt-6">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-sm text-muted-foreground">
                            {count(upload.tag_items_count, 'card')} carry the
                            tag{' '}
                            <TagChip
                                name={upload.tag.name}
                                color={upload.tag.color}
                            />
                            . Use it to find, re-tag or undo this batch.
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Button asChild variant="outline">
                                <Link
                                    href={collection.index({
                                        query: { tag: upload.tag.id },
                                    })}
                                >
                                    <GalleryVerticalEnd /> View imported cards
                                </Link>
                            </Button>
                            <Button
                                variant="outline"
                                className="text-destructive hover:text-destructive"
                                disabled={
                                    running || upload.tag_items_count === 0
                                }
                                onClick={() => setConfirmUndo(true)}
                            >
                                <Trash2 /> Delete the cards from this import
                            </Button>
                        </div>
                    </div>
                </Panel>
            )}

            {upload.failures.length > 0 && (
                <Panel
                    title="Skipped rows"
                    className="mt-6"
                    bodyClassName="p-0"
                >
                    <p className="border-b px-4 py-3 text-sm text-muted-foreground">
                        Fix these rows in your spreadsheet, save just them as a
                        new CSV, and import that file.
                        {upload.failures_truncated &&
                            ' Only the first 500 are listed.'}
                    </p>
                    <div className="max-h-[32rem] overflow-y-auto">
                        <table className="w-full text-sm">
                            <thead className="sticky top-0 border-b bg-muted text-left text-xs text-muted-foreground">
                                <tr>
                                    <th className="w-24 px-4 py-2 font-medium">
                                        Row
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Why it was skipped
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {upload.failures.map((failure) => (
                                    <tr key={failure.row}>
                                        <td className="px-4 py-2 font-medium tabular">
                                            {failure.row}
                                        </td>
                                        <td className="px-4 py-2">
                                            {failure.message}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Panel>
            )}

            <Dialog open={confirmUndo} onOpenChange={setConfirmUndo}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Delete {count(upload.tag_items_count, 'card')}?
                        </DialogTitle>
                        <DialogDescription>
                            Every card tagged “{upload.tag?.name}” is removed
                            from your collection, including any you tagged by
                            hand, along with their photos. Sets and checklists
                            stay. This can’t be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmUndo(false)}
                        >
                            Keep them
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                router.delete(
                                    imports.cards.destroy.url(upload.id),
                                    {
                                        onFinish: () => setConfirmUndo(false),
                                    },
                                )
                            }
                        >
                            Delete {count(upload.tag_items_count, 'card')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
