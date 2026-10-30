import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import invitations from '@/routes/invitations';

type Props = {
    token: string;
    email: string | null;
    inviter: string | null;
};

export default function AcceptInvitation({ token, email, inviter }: Props) {
    if (email === null) {
        return (
            <>
                <Head title="Invitation expired" />
                <div className="grid gap-4 text-sm">
                    <p>
                        This invitation link has expired or was already used.
                        Ask an admin to send you a new one.
                    </p>
                    <Link
                        href={login()}
                        className="font-medium text-primary hover:underline"
                    >
                        Go to log in
                    </Link>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Join" />

            <Form
                {...invitations.accept.form(token)}
                resetOnSuccess={['password', 'password_confirmation']}
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        {inviter && (
                            <p className="text-sm text-muted-foreground">
                                {inviter} invited you to the shared collection.
                            </p>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                value={email}
                                readOnly
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="name">Your name</Label>
                            <Input
                                id="name"
                                name="name"
                                autoComplete="name"
                                autoFocus
                                required
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">Password</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                required
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirm password
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                required
                            />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Create account
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

AcceptInvitation.layout = {
    title: 'Join the collection',
    description: 'Set your name and a password to get started.',
};
