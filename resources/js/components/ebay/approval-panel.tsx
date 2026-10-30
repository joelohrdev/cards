import { router } from '@inertiajs/react';
import { Check, Clock, X } from 'lucide-react';
import { useState } from 'react';
import { Field, Textarea } from '@/components/kit/field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { shortDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import ebay from '@/routes/ebay';

export type ApprovalDecision = 'pending' | 'approved' | 'rejected';

export type ListingApproval = {
    requested_by: string | null;
    requested_at: string | null;
    approved_at: string | null;
    approvals: {
        name: string;
        decision: ApprovalDecision;
        note: string | null;
        decided_at: string | null;
    }[];
    can_decide: boolean;
    can_withdraw: boolean;
};

const decisionIcons = {
    pending: Clock,
    approved: Check,
    rejected: X,
};

const decisionLabels: Record<ApprovalDecision, string> = {
    pending: 'Waiting',
    approved: 'Approved',
    rejected: 'Declined',
};

/**
 * Who has signed off on a listing, with approve, decline and withdraw controls.
 */
export function ApprovalPanel({
    listingId,
    approval,
    pending,
}: {
    listingId: number;
    approval: ListingApproval;
    pending: boolean;
}) {
    const [declining, setDeclining] = useState(false);
    const [note, setNote] = useState('');
    const [processing, setProcessing] = useState(false);

    function decide(approve: boolean) {
        router.post(
            ebay.listings.approval.decide.url(listingId),
            { approve, note: approve ? null : note || null },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setDeclining(false);
                    setNote('');
                },
            },
        );
    }

    return (
        <div className="space-y-3">
            {approval.requested_by && approval.requested_at && (
                <p className="text-sm text-muted-foreground">
                    {approval.requested_by} asked to list this on{' '}
                    {shortDate(approval.requested_at)}.
                </p>
            )}

            <ul className="space-y-1.5 text-sm">
                {approval.approvals.map((row) => {
                    const Icon = decisionIcons[row.decision];

                    return (
                        <li key={row.name} className="flex items-start gap-2">
                            <Icon
                                className={cn(
                                    'mt-0.5 size-4 shrink-0',
                                    row.decision === 'approved' && 'text-gain',
                                    row.decision === 'rejected' &&
                                        'text-destructive',
                                    row.decision === 'pending' &&
                                        'text-muted-foreground',
                                )}
                            />
                            <span className="min-w-0">
                                <span className="font-medium">{row.name}</span>{' '}
                                <span className="text-muted-foreground">
                                    {decisionLabels[row.decision]}
                                    {row.decided_at &&
                                        ` ${shortDate(row.decided_at)}`}
                                </span>
                                {row.note && (
                                    <span className="block text-muted-foreground">
                                        “{row.note}”
                                    </span>
                                )}
                            </span>
                        </li>
                    );
                })}
            </ul>

            {pending && (approval.can_decide || approval.can_withdraw) && (
                <div className="flex flex-wrap gap-2 pt-1">
                    {approval.can_decide && (
                        <>
                            <Button
                                disabled={processing}
                                onClick={() => decide(true)}
                            >
                                <Check /> Approve
                            </Button>
                            <Button
                                variant="outline"
                                disabled={processing}
                                onClick={() => setDeclining(true)}
                            >
                                Decline
                            </Button>
                        </>
                    )}
                    {approval.can_withdraw && (
                        <Button
                            variant="ghost"
                            disabled={processing}
                            onClick={() =>
                                router.delete(
                                    ebay.listings.approval.destroy.url(
                                        listingId,
                                    ),
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Withdraw request
                        </Button>
                    )}
                </div>
            )}

            <Dialog open={declining} onOpenChange={setDeclining}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Decline this listing?</DialogTitle>
                        <DialogDescription>
                            It goes back to a draft and won’t be listed on eBay.
                            A note helps whoever asked know what to change.
                        </DialogDescription>
                    </DialogHeader>
                    <Field label="Note (optional)" htmlFor="decline-note">
                        <Textarea
                            id="decline-note"
                            rows={3}
                            value={note}
                            maxLength={1000}
                            placeholder="e.g. Let’s hold it until after the show, or price it at $80"
                            onChange={(event) => setNote(event.target.value)}
                        />
                    </Field>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeclining(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={processing}
                            onClick={() => decide(false)}
                        >
                            Decline
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
