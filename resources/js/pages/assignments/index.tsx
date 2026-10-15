import { Link } from '@inertiajs/react';
import {
    DataTable,
    displayDate,
    EmptyState,
    OrganizationPage,
    Pagination,
    SearchFilters,
    StatusBadge,
} from '@/components/organization';
import { Button } from '@/components/ui/button';
import type { Assignment, Paginated, Person } from '@/types/organization';

export default function Assignments({
    assignments,
    unassigned,
    filters,
}: {
    assignments: Paginated<Assignment>;
    unassigned: Paginated<Person>;
    filters: { search?: string };
}) {
    return (
        <OrganizationPage
            title="Employee Assignments"
            description="Review current responsibilities, find available employees and preserve the history of each storage area."
            actions={
                <Button asChild>
                    <Link href="/storage-areas">
                        Assign through a storage area
                    </Link>
                </Button>
            }
        >
            <SearchFilters url="/assignments" filters={filters} />
            <section className="space-y-4">
                <h2 className="text-lg font-semibold">Current assignments</h2>
                {assignments.data.length ? (
                    <DataTable
                        headers={[
                            'Employee',
                            'Storage area',
                            'City',
                            'Area status',
                            'Assigned',
                            'Actions',
                        ]}
                    >
                        {assignments.data.map((assignment) => (
                            <tr key={assignment.id}>
                                <td>{assignment.user?.name}</td>
                                <td>
                                    {assignment.storage_area.name}
                                    <div className="text-xs text-muted-foreground">
                                        {assignment.storage_area.code}
                                    </div>
                                </td>
                                <td>{assignment.storage_area.city.name}</td>
                                <td>
                                    <StatusBadge
                                        active={
                                            assignment.storage_area.is_active
                                        }
                                    />
                                </td>
                                <td>{displayDate(assignment.assigned_at)}</td>
                                <td>
                                    <Link
                                        className="underline underline-offset-4"
                                        href={`/storage-areas/${assignment.storage_area_id}`}
                                    >
                                        Manage / history
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </DataTable>
                ) : (
                    <EmptyState>
                        No current assignments match this view.
                    </EmptyState>
                )}
                <Pagination page={assignments} />
            </section>
            <section className="space-y-4">
                <h2 className="text-lg font-semibold">
                    Unassigned stock employees
                </h2>
                <p className="text-sm text-muted-foreground">
                    Available employees across the organization. The search
                    above filters current assignments.
                </p>
                {unassigned.data.length ? (
                    <DataTable headers={['Employee', 'Email', 'Next step']}>
                        {unassigned.data.map((person) => (
                            <tr key={person.id}>
                                <td>{person.name}</td>
                                <td>{person.email}</td>
                                <td>
                                    <Link
                                        className="underline underline-offset-4"
                                        href="/storage-areas"
                                    >
                                        Choose storage area
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </DataTable>
                ) : (
                    <EmptyState>
                        No unassigned stock employees. Create an employee
                        account or end an existing assignment.
                    </EmptyState>
                )}
                <Pagination page={unassigned} />
            </section>
        </OrganizationPage>
    );
}
Assignments.layout = {
    breadcrumbs: [{ title: 'Employee Assignments', href: '/assignments' }],
};
