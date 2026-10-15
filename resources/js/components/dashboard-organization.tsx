import { Link } from '@inertiajs/react';
import {
    displayDate,
    EmptyState,
    StatusBadge,
} from '@/components/organization';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { Assignment, OrganizationStats } from '@/types/organization';

export default function DashboardOrganization({
    organization,
    assignment,
    isAdmin,
}: {
    organization: OrganizationStats | null;
    assignment: Assignment | null;
    isAdmin: boolean;
}) {
    if (isAdmin && organization) {
        const metrics = [
            ['Cities', organization.cities, '/cities'],
            [
                'Total storage areas',
                organization.storageAreas,
                '/storage-areas',
            ],
            [
                'Active storage areas',
                organization.activeStorageAreas,
                '/storage-areas?status=active',
            ],
            [
                'Assigned storage areas',
                organization.assignedStorageAreas,
                '/assignments',
            ],
            [
                'Unassigned storage areas',
                organization.unassignedStorageAreas,
                '/storage-areas',
            ],
        ] as const;
        return (
            <section
                aria-label="Organization overview"
                className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5"
            >
                {metrics.map(([title, count, href]) => (
                    <Card className="shadow-none" key={title}>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium">
                                <Link href={href} className="hover:underline">
                                    {title}
                                </Link>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-semibold tabular-nums">
                                {count}
                            </p>
                        </CardContent>
                    </Card>
                ))}
            </section>
        );
    }
    return (
        <section aria-label="Current responsibility">
            {assignment ? (
                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle>
                            <Link
                                className="hover:underline"
                                href="/my-storage-area"
                            >
                                {assignment.storage_area.name}
                            </Link>
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <p className="text-sm text-muted-foreground">
                            {assignment.storage_area.code} ·{' '}
                            {assignment.storage_area.city.name}
                        </p>
                        <div className="flex flex-wrap items-center gap-3">
                            <span className="text-sm">Current assignment</span>
                            <StatusBadge
                                active={assignment.storage_area.is_active}
                            />
                            <span className="text-sm text-muted-foreground">
                                Since {displayDate(assignment.assigned_at)}
                            </span>
                        </div>
                        <Link
                            href="/my-storage-area"
                            className="text-sm underline underline-offset-4"
                        >
                            View my storage area
                        </Link>
                    </CardContent>
                </Card>
            ) : (
                <EmptyState>
                    No storage area has been assigned to your account. Please
                    contact an administrator.
                </EmptyState>
            )}
        </section>
    );
}
