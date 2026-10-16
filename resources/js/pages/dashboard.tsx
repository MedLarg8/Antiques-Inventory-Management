import { Head, usePage } from '@inertiajs/react';
import { Activity } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import DashboardOrganization from '@/components/dashboard-organization';
import type { Assignment, OrganizationStats } from '@/types/organization';

export default function Dashboard({
    organization,
    assignment,
    inventory,
}: {
    organization: OrganizationStats | null;
    assignment: Assignment | null;
    inventory: Record<string, number | null>;
}) {
    const { auth } = usePage().props;
    const isAdmin = auth.user.role === 'admin';

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-8 p-6 lg:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="mb-2 text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                            {isAdmin
                                ? 'Central Administrative Bureau'
                                : 'Storage workspace'}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Dashboard
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Welcome, {auth.user.name}.{' '}
                            {isAdmin
                                ? 'Your organization overview starts here.'
                                : 'Your daily storage overview starts here.'}
                        </p>
                    </div>
                    <span className="rounded-full border bg-muted/50 px-3 py-1 text-xs font-medium">
                        {isAdmin ? 'Administrator' : 'Stock Employee'}
                    </span>
                </div>
                <DashboardOrganization
                    organization={organization}
                    assignment={assignment}
                    isAdmin={isAdmin}
                />
                <p className="text-sm text-muted-foreground">
                    Physical availability is shown only for initialized
                    balances. Low stock means recorded quantity is strictly
                    below its minimum. Stock operations and approvals are
                    planned.
                </p>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {Object.entries(inventory)
                        .filter(([, value]) => value !== null)
                        .map(([title, value]) => (
                            <Card key={title}>
                                <CardHeader>
                                    <CardTitle className="text-sm">
                                        {title}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="text-3xl font-semibold">
                                    {value}
                                </CardContent>
                            </Card>
                        ))}
                </div>
                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="text-base">
                            {isAdmin
                                ? 'Recent stock movements'
                                : 'Recent activity'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex min-h-48 flex-col items-center justify-center gap-3 text-center">
                        <Activity
                            className="size-7 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="text-sm font-medium">
                            Activity integration is planned
                        </p>
                        <p className="max-w-md text-sm leading-6 text-muted-foreground">
                            Authorized operations and their history will appear
                            here when the workflow is implemented.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
};
