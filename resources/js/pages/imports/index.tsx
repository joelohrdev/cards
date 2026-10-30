import { Form, Head, Link } from '@inertiajs/react';
import { FileSpreadsheet, Upload } from 'lucide-react';
import { useState } from 'react';
import { ImportStatusBadge } from '@/components/data/import-status-badge';
import { ProgressBar } from '@/components/data/progress-bar';
import type { ImportSummary } from '@/components/data/types';
import { EmptyState } from '@/components/kit/empty-state';
import { PageHeader } from '@/components/kit/page-header';
import { Panel } from '@/components/kit/panel';
import { Button } from '@/components/ui/button';
import { count, shortDate } from '@/lib/format';
import exportsRoutes from '@/routes/exports';
import imports from '@/routes/imports';

type Props = {
    imports: ImportSummary[];
    maxMegabytes: number;
};

export default function ImportsIndex({
    imports: history,
    maxMegabytes,
}: Props) {
    const [fileName, setFileName] = useState<string | null>(null);

    return (
        <>
            <Head title="Import cards" />

            <PageHeader
                title="Import cards"
                description="Bring in a spreadsheet of cards. You’ll match its columns to card fields before anything is saved."
            />

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <Panel title="Upload a CSV file">
                    <Form {...imports.store.form()} className="space-y-4">
                        {({ errors, processing, progress }) => (
                            <>
                                <label
                                    htmlFor="file"
                                    className="flex cursor-pointer flex-col items-center gap-3 rounded-lg border-2 border-dashed bg-muted/40 px-6 py-10 text-center transition-colors hover:border-primary/50 hover:bg-muted/70"
                                >
                                    <FileSpreadsheet
                                        className="size-8 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <span className="font-display text-xl font-semibold">
                                        {fileName ?? 'Choose a CSV file'}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        Up to {maxMegabytes} MB. The first row
                                        must be column headings.
                                    </span>
                                    <input
                                        id="file"
                                        name="file"
                                        type="file"
                                        accept=".csv,.tsv,.txt,text/csv"
                                        className="sr-only"
                                        onChange={(event) =>
                                            setFileName(
                                                event.target.files?.[0]?.name ??
                                                    null,
                                            )
                                        }
                                    />
                                </label>

                                {errors.file && (
                                    <p className="text-sm text-destructive">
                                        {errors.file}
                                    </p>
                                )}

                                {progress && (
                                    <ProgressBar
                                        value={progress.percentage ?? 0}
                                        max={100}
                                        label="Upload progress"
                                    />
                                )}

                                <Button
                                    type="submit"
                                    disabled={processing || !fileName}
                                >
                                    <Upload />{' '}
                                    {processing
                                        ? 'Uploading…'
                                        : 'Upload and match columns'}
                                </Button>
                            </>
                        )}
                    </Form>
                </Panel>

                <Panel title="Getting your file ready">
                    <ul className="space-y-3 text-sm text-muted-foreground">
                        <li>
                            <span className="font-medium text-foreground">
                                Excel or Numbers:
                            </span>{' '}
                            File → Save as → CSV.
                        </li>
                        <li>
                            <span className="font-medium text-foreground">
                                Google Sheets:
                            </span>{' '}
                            File → Download → Comma-separated values.
                        </li>
                        <li>
                            Each card needs at least a{' '}
                            <span className="font-medium text-foreground">
                                year
                            </span>
                            ,{' '}
                            <span className="font-medium text-foreground">
                                set
                            </span>{' '}
                            and{' '}
                            <span className="font-medium text-foreground">
                                card number
                            </span>
                            . Everything else is optional.
                        </li>
                        <li>
                            Every imported card gets a tag like “Import{' '}
                            {new Date().toISOString().slice(0, 10)}”, so you can
                            find or undo the whole batch later.
                        </li>
                        <li>
                            A file from{' '}
                            <Link
                                href={exportsRoutes.index()}
                                className="font-medium text-primary hover:underline"
                            >
                                Export
                            </Link>{' '}
                            can be imported back as-is.
                        </li>
                    </ul>
                </Panel>
            </div>

            <h2 className="mt-10 mb-3 font-display text-2xl font-bold">
                Past imports
            </h2>

            {history.length === 0 ? (
                <EmptyState
                    title="No imports yet"
                    description="Upload a CSV above. Nothing is added to your collection until you’ve checked the column matches."
                />
            ) : (
                <div className="overflow-x-auto rounded-lg border bg-card">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left text-xs text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">File</th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="hidden px-3 py-2 font-medium sm:table-cell">
                                    Progress
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Imported
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Skipped
                                </th>
                                <th className="hidden px-3 py-2 font-medium md:table-cell">
                                    Uploaded
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {history.map((entry) => (
                                <tr
                                    key={entry.id}
                                    className="hover:bg-muted/40"
                                >
                                    <td className="px-3 py-2">
                                        <Link
                                            href={
                                                entry.status === 'pending'
                                                    ? imports.mapping(entry.id)
                                                    : imports.show(entry.id)
                                            }
                                            className="font-medium hover:text-primary hover:underline"
                                        >
                                            {entry.original_filename}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">
                                            {count(entry.total_rows, 'row')}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2">
                                        <ImportStatusBadge
                                            status={entry.status}
                                        />
                                    </td>
                                    <td className="hidden w-48 px-3 py-2 sm:table-cell">
                                        <ProgressBar
                                            value={entry.processed_rows}
                                            max={entry.total_rows}
                                            label={`${entry.original_filename} progress`}
                                        />
                                    </td>
                                    <td className="px-3 py-2 text-right tabular">
                                        {entry.imported_rows.toLocaleString()}
                                    </td>
                                    <td className="px-3 py-2 text-right text-muted-foreground tabular">
                                        {entry.failed_rows.toLocaleString()}
                                    </td>
                                    <td className="hidden px-3 py-2 text-muted-foreground md:table-cell">
                                        {shortDate(entry.created_at)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </>
    );
}
