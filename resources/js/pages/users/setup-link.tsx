import { Link } from '@inertiajs/react';
import { OrganizationPage } from '@/components/organization';
import { Input } from '@/components/ui/input';
export default function SetupLink({
    employee,
    setupUrl,
    verificationUrl,
    expiresMinutes,
}: {
    employee: { id: number; name: string; email: string };
    setupUrl: string;
    verificationUrl: string | null;
    expiresMinutes: number;
}) {
    return (
        <OrganizationPage
            title="Private first-login handoff"
            description={
                'For ' +
                employee.name +
                ' (' +
                employee.email +
                '). Confirm the recipient’s identity and deliver these links privately.'
            }
        >
            <div className="max-w-3xl space-y-6 rounded border p-6">
                <p>
                    These links grant account setup access. Do not put them in
                    tickets, shared documents or ordinary logs. This page cannot
                    be reopened by refreshing it.
                </p>
                <label className="block space-y-2">
                    <span className="font-medium">
                        Password setup/reset link — expires in {expiresMinutes}{' '}
                        minutes
                    </span>
                    <Input
                        readOnly
                        value={setupUrl}
                        onFocus={(e) => e.target.select()}
                    />
                </label>
                <p className="text-sm text-muted-foreground">
                    The employee opens the link on their own device, chooses
                    their own password, and signs in. A successful reset
                    invalidates the token. Generating a replacement invalidates
                    the previous link.
                </p>
                {verificationUrl && (
                    <>
                        <label className="block space-y-2">
                            <span className="font-medium">
                                Email verification link — expires in 60 minutes
                            </span>
                            <Input
                                readOnly
                                value={verificationUrl}
                                onFocus={(e) => e.target.select()}
                            />
                        </label>
                        <p className="text-sm text-muted-foreground">
                            After signing in, the employee opens this link in
                            the same browser. Deliver it only after confirming
                            their identity and work email through your internal
                            procedure. It uses the normal signed
                            email-verification route.
                        </p>
                    </>
                )}
                <Link className="underline" href={'/users/' + employee.id}>
                    Close and return to employee details
                </Link>
            </div>
        </OrganizationPage>
    );
}
SetupLink.layout = {
    breadcrumbs: [
        { title: 'Employee directory', href: '/users' },
        { title: 'Private onboarding', href: '#' },
    ],
};
