import { router } from '@inertiajs/react';
import { Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { NativeSelect } from '@/components/kit/field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import collection from '@/routes/collection';
import type { FormOptions } from '@/types';

type Action = 'tag' | 'untag' | 'move' | 'status' | 'value' | 'delete';

/**
 * Floating action bar for the selected cards.
 */
export function BulkBar({
    ids,
    options,
    onClear,
}: {
    ids: number[];
    options: FormOptions;
    onClear: () => void;
}) {
    const [action, setAction] = useState<Action | ''>('');
    const [value, setValue] = useState('');
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [processing, setProcessing] = useState(false);

    function submit(chosen: Action, payloadValue = value) {
        const payload: Record<string, unknown> = { ids, action: chosen };

        if (chosen === 'tag' || chosen === 'untag') {
            payload.tags = payloadValue
                .split(',')
                .map((tag) => tag.trim())
                .filter(Boolean);
        } else if (chosen === 'move') {
            payload.storage_location_id = payloadValue || null;
        } else if (chosen === 'status') {
            payload.status = payloadValue;
        } else if (chosen === 'value') {
            payload.estimated_value = payloadValue;
        }

        router.post(collection.bulk.url(), payload as never, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => {
                setAction('');
                setValue('');
                setConfirmDelete(false);
                onClear();
            },
        });
    }

    return (
        <div className="no-print fixed inset-x-0 bottom-4 z-40 flex justify-center px-4">
            <div className="flex w-full max-w-4xl flex-wrap items-center gap-2 rounded-xl bg-rail p-2 pl-4 text-rail-foreground shadow-2xl">
                <span className="mr-1 font-display text-lg font-semibold tabular">
                    {ids.length.toLocaleString()} selected
                </span>

                <NativeSelect
                    value={action}
                    onChange={(event) => {
                        setAction(event.target.value as Action | '');
                        setValue('');
                    }}
                    className="w-auto border-white/15 bg-rail-active text-rail-foreground"
                    aria-label="Bulk action"
                >
                    <option value="">Choose an action…</option>
                    <option value="tag">Add tags</option>
                    <option value="untag">Remove tags</option>
                    <option value="move">Move to storage</option>
                    <option value="status">Change status</option>
                    <option value="value">Set value</option>
                </NativeSelect>

                {(action === 'tag' || action === 'untag') && (
                    <Input
                        list="bulk-tag-options"
                        value={value}
                        onChange={(event) => setValue(event.target.value)}
                        placeholder="Tag names, comma separated"
                        className="w-56 border-white/15 bg-rail-active text-rail-foreground placeholder:text-rail-muted"
                    />
                )}
                <datalist id="bulk-tag-options">
                    {options.tags.map((tag) => (
                        <option key={tag.id} value={tag.name} />
                    ))}
                </datalist>

                {action === 'move' && (
                    <NativeSelect
                        value={value}
                        onChange={(event) => setValue(event.target.value)}
                        className="w-56 border-white/15 bg-rail-active text-rail-foreground"
                        aria-label="Storage location"
                    >
                        <option value="">Not placed</option>
                        {options.locations.map((location) => (
                            <option key={location.id} value={location.id}>
                                {location.path}
                            </option>
                        ))}
                    </NativeSelect>
                )}

                {action === 'status' && (
                    <NativeSelect
                        value={value}
                        onChange={(event) => setValue(event.target.value)}
                        className="w-44 border-white/15 bg-rail-active text-rail-foreground"
                        aria-label="Status"
                    >
                        <option value="">Choose…</option>
                        {options.statuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </NativeSelect>
                )}

                {action === 'value' && (
                    <Input
                        inputMode="decimal"
                        value={value}
                        onChange={(event) => setValue(event.target.value)}
                        placeholder="Value each, e.g. 2.50"
                        className="w-44 border-white/15 bg-rail-active text-rail-foreground placeholder:text-rail-muted"
                    />
                )}

                {action && (
                    <Button
                        size="sm"
                        variant="secondary"
                        disabled={
                            processing ||
                            (action !== 'move' && value.trim() === '')
                        }
                        onClick={() => submit(action)}
                    >
                        Apply
                    </Button>
                )}

                <div className="ml-auto flex items-center gap-1">
                    <Button
                        size="sm"
                        variant="ghost"
                        className="text-rail-foreground hover:bg-rail-active hover:text-rail-foreground"
                        onClick={() => setConfirmDelete(true)}
                    >
                        <Trash2 /> Delete
                    </Button>
                    <Button
                        size="icon"
                        variant="ghost"
                        className="text-rail-foreground hover:bg-rail-active hover:text-rail-foreground"
                        onClick={onClear}
                        aria-label="Clear selection"
                    >
                        <X />
                    </Button>
                </div>
            </div>

            <Dialog open={confirmDelete} onOpenChange={setConfirmDelete}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Delete {ids.length.toLocaleString()} cards?
                        </DialogTitle>
                        <DialogDescription>
                            This removes them from your collection along with
                            their photos, sales and listing history. To keep the
                            history, change their status to Sold, Traded or
                            Gifted instead.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmDelete(false)}
                        >
                            Keep them
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={processing}
                            onClick={() => submit('delete')}
                        >
                            Delete cards
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
