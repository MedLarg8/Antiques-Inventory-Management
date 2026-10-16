import { Link, useForm } from '@inertiajs/react';
import {
    OrganizationPage,
    DataTable,
    EmptyState,
    Pagination,
    StatusBadge,
    displayDate,
    selectClass,
} from '@/components/organization';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import InputError from '@/components/input-error';
import type { Employee } from '@/types/employees';
import type { Paginated } from '@/types/organization';
export default function Users({
    users,
    filters,
    roles,
    cities,
    areas,
}: {
    users: Paginated<Employee>;
    filters: Record<string, string>;
    roles: Record<string, string>;
    cities: { id: number; name: string }[];
    areas: { id: number; name: string }[];
}) {
    const form = useForm({
        search: filters.search ?? '',
        role: filters.role ?? '',
        status: filters.status ?? '',
        assignment: filters.assignment ?? '',
        city_id: filters.city_id ?? '',
        storage_area_id: filters.storage_area_id ?? '',
        sort: filters.sort ?? 'name',
        direction: filters.direction ?? 'asc',
    });
    const choices: Record<string, [string, string][]> = {
        role: [['', 'All roles'], ...Object.entries(roles)],
        status: [
            ['', 'All account statuses'],
            ['active', 'Active'],
            ['inactive', 'Inactive'],
        ],
        assignment: [
            ['', 'All responsibility statuses'],
            ['assigned', 'Assigned'],
            ['unassigned', 'Unassigned'],
        ],
        city_id: [
            ['', 'All cities'],
            ...cities.map((c) => [String(c.id), c.name] as [string, string]),
        ],
        storage_area_id: [
            ['', 'All depots'],
            ...areas.map((a) => [String(a.id), a.name] as [string, string]),
        ],
        sort: [
            ['name', 'Name'],
            ['email', 'Email'],
            ['created_at', 'Date created'],
            ['last_login_at', 'Last login'],
        ],
        direction: [
            ['asc', 'Ascending'],
            ['desc', 'Descending'],
        ],
    };
    const labels: Record<string, string> = {
        role: 'Role',
        status: 'Account status',
        assignment: 'Depot assignment',
        city_id: 'City',
        storage_area_id: 'Depot',
        sort: 'Sort by',
        direction: 'Sort direction',
    };
    return (
        <OrganizationPage
            title="Employee directory"
            description="Manage accounts, permissions and depot responsibility."
            actions={
                <Button asChild>
                    <Link href="/users/create">Create employee</Link>
                </Button>
            }
        >
            <form
                className="grid gap-4 rounded border p-4 sm:grid-cols-2 xl:grid-cols-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.get('/users', { preserveState: true });
                }}
            >
                <label className="space-y-2">
                    <span>Search name or email</span>
                    <Input
                        value={form.data.search}
                        onChange={(e) => form.setData('search', e.target.value)}
                        maxLength={100}
                    />
                </label>
                {(Object.keys(choices) as (keyof typeof form.data)[]).map(
                    (k) => (
                        <label key={k} className="space-y-2">
                            <span>{labels[k]}</span>
                            <select
                                className={selectClass}
                                value={form.data[k]}
                                onChange={(e) =>
                                    form.setData(k, e.target.value)
                                }
                            >
                                {choices[k].map(([v, l]) => (
                                    <option key={v} value={v}>
                                        {l}
                                    </option>
                                ))}
                            </select>
                        </label>
                    ),
                )}
                <div className="flex gap-3">
                    <Button disabled={form.processing}>Apply filters</Button>
                    <Link href="/users" className="self-center underline">
                        Clear
                    </Link>
                </div>
                {Object.values(form.errors).map((e) => (
                    <InputError key={e} message={e} />
                ))}
            </form>
            {users.data.length ? (
                <DataTable
                    headers={[
                        'Name / email',
                        'Role',
                        'Account access',
                        'Depot / city',
                        'Created',
                        'Last login',
                        'Actions',
                    ]}
                >
                    {users.data.map((u) => (
                        <tr key={u.id}>
                            <td>
                                <Link
                                    className="font-medium underline"
                                    href={'/users/' + u.id}
                                >
                                    {u.name}
                                </Link>
                                <p className="text-xs text-muted-foreground">
                                    {u.email}
                                </p>
                            </td>
                            <td>{roles[u.role]}</td>
                            <td>
                                <StatusBadge active={u.is_active} />
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {u.email_verified_at
                                        ? 'Email verified'
                                        : 'Awaiting verification'}
                                </p>
                            </td>
                            <td>
                                {u.current_storage_area
                                    ? u.current_storage_area.city.name +
                                      ' / ' +
                                      u.current_storage_area.name
                                    : 'Unassigned'}
                            </td>
                            <td>{displayDate(u.created_at)}</td>
                            <td>
                                {u.last_login_at
                                    ? displayDate(u.last_login_at)
                                    : 'Not recorded'}
                            </td>
                            <td>
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={'/users/' + u.id}>Manage</Link>
                                </Button>
                            </td>
                        </tr>
                    ))}
                </DataTable>
            ) : (
                <EmptyState>No employees match these filters.</EmptyState>
            )}
            <Pagination page={users} />
        </OrganizationPage>
    );
}
Users.layout = {
    breadcrumbs: [{ title: 'Employee directory', href: '/users' }],
};
