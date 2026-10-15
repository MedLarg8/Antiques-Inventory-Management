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
import type { City, Paginated } from '@/types/organization';

export default function Cities({
    cities,
    filters,
}: {
    cities: Paginated<City>;
    filters: { search?: string };
}) {
    return (
        <OrganizationPage
            title="Cities"
            description="Manage the cities containing your organization's physical storage areas."
            actions={
                <Button asChild>
                    <Link href="/cities/create">Create city</Link>
                </Button>
            }
        >
            <SearchFilters url="/cities" filters={filters} />
            {cities.data.length ? (
                <DataTable
                    headers={[
                        'City',
                        'Code',
                        'Status',
                        'Storage areas',
                        'Actions',
                    ]}
                >
                    {cities.data.map((city) => (
                        <tr key={city.id}>
                            <td className="font-medium">{city.name}</td>
                            <td>{city.code ?? '—'}</td>
                            <td>
                                <StatusBadge active={city.is_active} />
                            </td>
                            <td>
                                <Link
                                    className="underline underline-offset-4"
                                    href={`/storage-areas?city_id=${city.id}`}
                                >
                                    {city.storage_areas_count} storage areas
                                </Link>
                            </td>
                            <td>
                                <Link
                                    className="underline underline-offset-4"
                                    href={`/cities/${city.id}/edit`}
                                >
                                    Edit
                                    <span className="sr-only">
                                        {' '}
                                        {city.name}
                                    </span>
                                </Link>
                            </td>
                        </tr>
                    ))}
                </DataTable>
            ) : (
                <EmptyState>
                    No cities match this view. Create a city or clear the
                    search.
                </EmptyState>
            )}
            <Pagination page={cities} />
        </OrganizationPage>
    );
}
Cities.layout = { breadcrumbs: [{ title: 'Cities', href: '/cities' }] };
