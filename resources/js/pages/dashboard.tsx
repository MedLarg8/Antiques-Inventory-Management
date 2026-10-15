import { Head, usePage } from '@inertiajs/react';
import { Activity, Archive, ClipboardCheck, TriangleAlert } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import DashboardOrganization from '@/components/dashboard-organization';
import type { Assignment, OrganizationStats } from '@/types/organization';

const adminWidgets = [
    {
        title: 'Total inventory items',
        detail: 'Inventory totals will appear once records are connected.',
        icon: Archive,
    },
    {
        title: 'Pending approvals',
        detail: 'Requests awaiting bureau review will appear here.',
        icon: ClipboardCheck,
    },
    {
        title: 'Low-stock alerts',
        detail: 'Alerts will be available when stock rules are defined.',
        icon: TriangleAlert,
    },
];
const employeeWidgets = [
    {
        title: 'Inventory summary',
        detail: 'Your assigned inventory summary will appear here.',
        icon: Archive,
    },
    {
        title: 'Pending requests',
        detail: 'Your submitted request statuses will appear here.',
        icon: ClipboardCheck,
    },
    {
        title: 'Low-stock alerts',
        detail: 'Alerts will be available when stock rules are defined.',
        icon: TriangleAlert,
    },
];

export default function Dashboard({
    organization,
    assignment,
}: {
    organization: OrganizationStats | null;
    assignment: Assignment | null;
}) {
    const { auth } = usePage().props;
    const isAdmin = auth.user.role === 'admin';
    const widgets = isAdmin ? adminWidgets : employeeWidgets;

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
                <div
                    role="note"
                    className="rounded-lg border bg-muted/30 px-4 py-3 text-sm leading-6 text-muted-foreground"
                >
                    <span className="font-medium text-foreground">
                        Inventory modules are planned.
                    </span>{' '}
                    Organizational information above comes from the database.
                    Inventory and approval sections below remain placeholders;
                    stock operations are not available yet.
                </div>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {widgets.map(({ title, detail, icon: Icon }) => (
                        <Card key={title} className="shadow-none">
                            <CardHeader>
                                <div className="mb-4 flex items-center justify-between">
                                    <Icon
                                        className="size-5 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <span className="text-xs text-muted-foreground">
                                        Planned
                                    </span>
                                </div>
                                <CardTitle className="text-sm font-medium">
                                    {title}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm leading-6 text-muted-foreground">
                                    {detail}
                                </p>
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
