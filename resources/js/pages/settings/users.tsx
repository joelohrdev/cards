import { Form, Head, router, usePage } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { Label } from '@/components/ui/label';
import { shortDate } from '@/lib/format';
import users from '@/routes/users';
import type { Option, UserRole } from '@/types';

type Member = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    is_you: boolean;
    joined_at: string | null;
};

type PendingInvitation = {
    id: number;
    email: string;
    role: UserRole;
    expires_at: string;
};

type Props = {
    members: Member[];
    invitations: PendingInvitation[];
    roles: Option[];
};

const roleLabels: Record<UserRole, string> = {
    admin: 'Admin',
    member: 'Member',
};

export default function Users({ members, invitations, roles }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const invitationUrl = usePage().flash.invitationUrl as string | undefined;
    const [removing, setRemoving] = useState<Member | null>(null);

    function changeRole(member: Member, role: string) {
        router.patch(
            users.update.url(member.id),
            { role },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Users" />

            <h1 className="sr-only">Users</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Invite someone"
                    description="They’ll get an email with a link to set up their account. Admins can also invite and manage people."
                />

                <Form
                    {...users.invitations.store.form()}
                    resetOnSuccess={['email']}
                    options={{ preserveScroll: true }}
                    className="grid gap-3 sm:grid-cols-[1fr_9rem_auto] sm:items-start"
                >
                    {({ processing, errors: formErrors }) => (
                        <>
                            <div className="grid gap-1.5">
                                <Label htmlFor="email" className="sr-only">
                                    Email
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    placeholder="name@example.com"
                                    required
                                />
                                <InputError message={formErrors.email} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="role" className="sr-only">
                                    Role
                                </Label>
                                <NativeSelect
                                    id="role"
                                    name="role"
                                    defaultValue="member"
                                >
                                    {roles.map((role) => (
                                        <option
                                            key={role.value}
                                            value={role.value}
                                        >
                                            {role.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>
                            <Button type="submit" disabled={processing}>
                                Send invite
                            </Button>
                        </>
                    )}
                </Form>

                {invitationUrl && <InvitationLink url={invitationUrl} />}
            </div>

            {invitations.length > 0 && (
                <div className="space-y-3">
                    <Heading
                        variant="small"
                        title="Waiting to join"
                        description="Invitation links work once and expire after a week."
                    />
                    <ul className="divide-y rounded-lg border">
                        {invitations.map((invitation) => (
                            <li
                                key={invitation.id}
                                className="flex flex-wrap items-center gap-3 px-4 py-3 text-sm"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium">
                                        {invitation.email}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {roleLabels[invitation.role]} · expires{' '}
                                        {shortDate(invitation.expires_at)}
                                    </p>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        router.delete(
                                            users.invitations.destroy.url(
                                                invitation.id,
                                            ),
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Cancel
                                </Button>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="space-y-3">
                <Heading
                    variant="small"
                    title="Current users"
                    description="Everyone here sees the whole collection. Selling a card on eBay needs approval from the other members; admins aren’t asked."
                />
                <InputError message={errors.member} />
                <ul className="divide-y rounded-lg border">
                    {members.map((member) => (
                        <li
                            key={member.id}
                            className="flex flex-wrap items-center gap-3 px-4 py-3 text-sm"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-medium">
                                    {member.name}
                                    {member.is_you && (
                                        <span className="font-normal text-muted-foreground">
                                            {' '}
                                            (you)
                                        </span>
                                    )}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {member.email}
                                </p>
                            </div>
                            {member.is_you ? (
                                <span className="text-muted-foreground">
                                    {roleLabels[member.role]}
                                </span>
                            ) : (
                                <>
                                    <NativeSelect
                                        aria-label={`Role for ${member.name}`}
                                        value={member.role}
                                        onChange={(event) =>
                                            changeRole(
                                                member,
                                                event.target.value,
                                            )
                                        }
                                        className="w-32"
                                    >
                                        {roles.map((role) => (
                                            <option
                                                key={role.value}
                                                value={role.value}
                                            >
                                                {role.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setRemoving(member)}
                                    >
                                        Remove
                                    </Button>
                                </>
                            )}
                        </li>
                    ))}
                </ul>
            </div>

            <Dialog
                open={removing !== null}
                onOpenChange={(open) => !open && setRemoving(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Remove {removing?.name}?</DialogTitle>
                        <DialogDescription>
                            They won’t be able to sign in any more. The cards,
                            sales and other records they added stay in the
                            collection.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setRemoving(null)}
                        >
                            Keep them
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (removing) {
                                    router.delete(
                                        users.destroy.url(removing.id),
                                        {
                                            preserveScroll: true,
                                            onFinish: () => setRemoving(null),
                                        },
                                    );
                                }
                            }}
                        >
                            Remove
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

/**
 * The link from the invite just sent, in case the email doesn't arrive.
 */
function InvitationLink({ url }: { url: string }) {
    const [copied, setCopied] = useState(false);

    return (
        <div className="space-y-1.5 rounded-lg border bg-muted/40 p-3 text-sm">
            <p className="text-muted-foreground">
                Email not arriving? Send them this link yourself:
            </p>
            <div className="flex gap-2">
                <Input readOnly value={url} className="font-mono text-xs" />
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label="Copy link"
                    onClick={() => {
                        void navigator.clipboard.writeText(url);
                        setCopied(true);
                    }}
                >
                    {copied ? <Check /> : <Copy />}
                </Button>
            </div>
        </div>
    );
}

Users.layout = {
    breadcrumbs: [{ title: 'Users', href: users.index() }],
};
