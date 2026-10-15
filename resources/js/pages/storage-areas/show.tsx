import { Link } from '@inertiajs/react';
import AssignmentManager from '@/components/assignment-manager';
import {
    DataTable,
    displayDate,
    EmptyState,
    OrganizationPage,
    Pagination,
    StatusBadge,
} from '@/components/organization';
import { Button } from '@/components/ui/button';
import type {
    Assignment,
    Paginated,
    Person,
    StorageArea,
} from '@/types/organization';

export default function StorageAreaShow({
    area,
    history,
    employees,
    canManage,
}: {
    area: StorageArea;
    history: Paginated<Assignment> | null;
    employees: Person[];
    canManage: boolean;
}) {
    return (
        <OrganizationPage
            title={area.name}
            description={area.code + ' · ' + area.city.name}
            actions={
                canManage && (
                    <Button variant="outline" asChild>
                        <Link href={`/storage-areas/${area.id}/edit`}>
                            Edit storage area
                        </Link>
                    </Button>
                )
            }
        >
            <dl className="grid gap-6 rounded-xl border p-6 sm:grid-cols-2">
                <div>
                    <dt className="text-sm text-muted-foreground">
                        Storage-area status
                    </dt>
                    <dd className="mt-2">
                        <StatusBadge active={area.is_active} />
                    </dd>
                </div>
                <div>
                    <dt className="text-sm text-muted-foreground">City</dt>
                    <dd className="mt-2">
                        {area.city.name}{' '}
                        {!area.city.is_active && (
                            <span className="text-sm text-muted-foreground">
                                (inactive city)
                            </span>
                        )}
                    </dd>
                </div>
                <div>
                    <dt className="text-sm text-muted-foreground">
                        Location details
                    </dt>
                    <dd className="mt-2 whitespace-pre-wrap text-sm leading-6">
                        {area.location_details || 'Not provided'}
                    </dd>
                </div>
                <div>
                    <dt className="text-sm text-muted-foreground">
                        Description
                    </dt>
                    <dd className="mt-2 whitespace-pre-wrap text-sm leading-6">
                        {area.description || 'Not provided'}
                    </dd>
                </div>
                <div>
                    <dt className="text-sm text-muted-foreground">
                        Responsible employee
                    </dt>
                    <dd className="mt-2 font-medium">
                        {area.current_assignment?.user?.name ?? 'Unassigned'}
                    </dd>
                </div>
                <div>
                    <dt className="text-sm text-muted-foreground">
                        Assigned since
                    </dt>
                    <dd className="mt-2 text-sm">
                        {displayDate(area.current_assignment?.assigned_at)}
                    </dd>
                </div>
            </dl>
            {canManage && (
                <AssignmentManager
                    key={`${area.id}-${area.current_assignment?.id ?? 'none'}`}
                    area={area}
                    employees={employees}
                />
            )}
            {canManage && history && (
                <section className="space-y-4">
                    <h2 className="text-lg font-semibold">
                        Previous assignments
                    </h2>
                    {history.data.length ? (
                        <DataTable headers={['Employee', 'Assigned', 'Ended']}>
                            {history.data.map((assignment) => (
                                <tr key={assignment.id}>
                                    <td>{assignment.user?.name}</td>
                                    <td>
                                        {displayDate(assignment.assigned_at)}
                                    </td>
                                    <td>{displayDate(assignment.ended_at)}</td>
                                </tr>
                            ))}
                        </DataTable>
                    ) : (
                        <EmptyState>
                            No previous assignments for this storage area.
                        </EmptyState>
                    )}
                    <Pagination page={history} />
                </section>
            )}
            <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                <strong className="text-foreground">
                    Inventory — planned.
                </strong>{' '}
                Items, quantities and stock operations are not implemented yet.
            </div>
        </OrganizationPage>
    );
}
StorageAreaShow.layout = {
    breadcrumbs: [{ title: 'Storage area details', href: '#' }],
};
