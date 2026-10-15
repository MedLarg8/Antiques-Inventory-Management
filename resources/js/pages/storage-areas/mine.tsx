import {
    displayDate,
    EmptyState,
    OrganizationPage,
    StatusBadge,
} from '@/components/organization';
import type { Assignment } from '@/types/organization';

export default function MyStorageArea({
    assignment,
}: {
    assignment: Assignment | null;
}) {
    const area = assignment?.storage_area;
    return (
        <OrganizationPage
            title="My Storage Area"
            description="Your current responsibility and the details of your assigned physical location."
        >
            {area ? (
                <>
                    <dl className="grid gap-6 rounded-xl border p-6 sm:grid-cols-2">
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Storage area
                            </dt>
                            <dd className="mt-2 text-lg font-semibold">
                                {area.name}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Code
                            </dt>
                            <dd className="mt-2">{area.code}</dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                City
                            </dt>
                            <dd className="mt-2">{area.city.name}</dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Area status
                            </dt>
                            <dd className="mt-2">
                                <StatusBadge active={area.is_active} />
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
                                Assignment started
                            </dt>
                            <dd className="mt-2 text-sm">
                                {displayDate(assignment.assigned_at)}
                            </dd>
                        </div>
                    </dl>
                    <p className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                        Inventory and stock operations will be available in a
                        later batch.
                    </p>
                </>
            ) : (
                <EmptyState>
                    No storage area has been assigned to your account. Please
                    contact an administrator.
                </EmptyState>
            )}
        </OrganizationPage>
    );
}
MyStorageArea.layout = {
    breadcrumbs: [{ title: 'My Storage Area', href: '/my-storage-area' }],
};
