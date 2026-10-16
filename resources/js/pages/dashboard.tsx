import { Head, usePage } from '@inertiajs/react';
import DashboardStock from '@/components/dashboard-stock';
import type { StockSummary } from '@/types/stock';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import DashboardOrganization from '@/components/dashboard-organization';
import type { Assignment, OrganizationStats } from '@/types/organization';

export default function Dashboard({
    organization,
    assignment,
    inventory,
    employees,
    stock,
}: {
    organization: OrganizationStats | null;
    assignment: Assignment | null;
    inventory: Record<string, number | null>;
    employees: Record<string, number> | null;
    stock: StockSummary;
}) {
    const { auth } = usePage().props;
    const isAdmin = auth.canAdministrate;

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
                {employees && (
                    <section
                        aria-label="Employee administration"
                        className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
                    >
                        {Object.entries(employees).map(([title, value]) => (
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
                    </section>
                )}
                <p className="text-sm text-muted-foreground">
                    Physical availability is shown only for initialized
                    balances. Low stock means recorded quantity is strictly
                    below its minimum. Stock changes only when an approved
                    operation is executed.
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
                <DashboardStock stock={stock} />
            </div>
        </>
    );
}
Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
};
