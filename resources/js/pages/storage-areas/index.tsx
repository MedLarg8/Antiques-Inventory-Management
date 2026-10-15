import { Link } from '@inertiajs/react';
import {
    DataTable,
    EmptyState,
    OrganizationPage,
    Pagination,
    SearchFilters,
    StatusBadge,
} from '@/components/organization';
import { Button } from '@/components/ui/button';
import type { City, Paginated, StorageArea } from '@/types/organization';
export default function StorageAreas({
    areas,
    cities,
    filters,
}: {
    areas: Paginated<StorageArea>;
    cities: City[];
    filters: { search?: string; city_id?: string; status?: string };
}) {
    return (
        <OrganizationPage
            title="Storage Areas"
            description="Physical locations holding items and equipment. The administrative bureau is not a storage area."
            actions={
                <Button asChild>
                    <Link href="/storage-areas/create">
                        Create storage area
                    </Link>
                </Button>
            }
        >
            <SearchFilters
                url="/storage-areas"
                filters={filters}
                cities={cities}
            />
            {areas.data.length ? (
                <DataTable
                    headers={[
                        'Name / code',
                        'City',
                        'Status',
                        'Responsible employee',
                        'Actions',
                    ]}
                >
                    {areas.data.map((area) => (
                        <tr key={area.id}>
                            <td>
                                <Link
                                    className="font-medium underline underline-offset-4"
                                    href={`/storage-areas/${area.id}`}
                                >
                                    {area.name}
                                </Link>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {area.code}
                                </div>
                            </td>
                            <td>{area.city.name}</td>
                            <td>
                                <StatusBadge active={area.is_active} />
                            </td>
                            <td>
                                {area.current_assignment?.user?.name ??
                                    'Unassigned'}
                            </td>
                            <td>
                                <Link
                                    className="underline underline-offset-4"
                                    href={`/storage-areas/${area.id}/edit`}
                                >
                                    Edit
                                    <span className="sr-only">
                                        {' '}
                                        {area.name}
                                    </span>
                                </Link>
                            </td>
                        </tr>
                    ))}
                </DataTable>
            ) : (
                <EmptyState>
                    No storage areas match these filters. Create a storage area
                    or clear the filters.
                </EmptyState>
            )}
            <Pagination page={areas} />
        </OrganizationPage>
    );
}
StorageAreas.layout = {
    breadcrumbs: [{ title: 'Storage Areas', href: '/storage-areas' }],
};
