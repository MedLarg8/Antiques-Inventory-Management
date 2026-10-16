import { Link, useForm } from '@inertiajs/react';
import {
    OrganizationPage,
    DataTable,
    Pagination,
    EmptyState,
    StatusBadge,
    selectClass,
} from '@/components/organization';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Category, Item } from '@/types/inventory';
import { modes } from '@/types/inventory';
import type { Paginated } from '@/types/organization';
export default function Inventory({
    items,
    categories,
    filters,
    canManage,
}: {
    items: Paginated<Item>;
    categories: Category[];
    filters: Record<string, string>;
    canManage: boolean;
}) {
    const form = useForm({
        search: filters.search ?? '',
        category_id: filters.category_id ?? '',
        tracking_mode: filters.tracking_mode ?? '',
        status: filters.status ?? '',
    });
    return (
        <OrganizationPage
            title="Inventory catalog"
            description="Shared item definitions and authorized recorded inventory."
            actions={
                <div className="flex gap-4">
                    <Link href="/categories" className="underline">
                        Categories
                    </Link>
                    {canManage && (
                        <Button asChild>
                            <Link href="/catalog/items/create">
                                Create item
                            </Link>
                        </Button>
                    )}
                </div>
            }
        >
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.get('/inventory');
                }}
                className="grid gap-3 rounded-lg border p-4 sm:grid-cols-5"
            >
                <label>
                    Search
                    <Input
                        value={form.data.search}
                        onChange={(e) => form.setData('search', e.target.value)}
                        placeholder="Name or reference"
                    />
                </label>
                <label>
                    Category
                    <select
                        className={selectClass}
                        value={form.data.category_id}
                        onChange={(e) =>
                            form.setData('category_id', e.target.value)
                        }
                    >
                        <option value="">All categories</option>
                        {categories.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </select>
                </label>
                <label>
                    Tracking mode
                    <select
                        className={selectClass}
                        value={form.data.tracking_mode}
                        onChange={(e) =>
                            form.setData('tracking_mode', e.target.value)
                        }
                    >
                        <option value="">All modes</option>
                        {Object.entries(modes).map(([k, v]) => (
                            <option key={k} value={k}>
                                {v}
                            </option>
                        ))}
                    </select>
                </label>
                {canManage && (
                    <label>
                        Status
                        <select
                            className={selectClass}
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </label>
                )}
                <Button className="self-end" disabled={form.processing}>
                    Apply filters
                </Button>
                {Object.values(form.errors).map((e) => (
                    <p key={e} className="text-sm text-destructive">
                        {e}
                    </p>
                ))}
            </form>
            {items.data.length ? (
                <DataTable
                    headers={[
                        'Photo',
                        'Reference / name',
                        'Category',
                        'Tracking',
                        'Unit',
                        'Status',
                    ]}
                >
                    {items.data.map((i) => (
                        <tr key={i.id}>
                            <td>
                                {i.thumbnail ? (
                                    <img
                                        src={i.thumbnail}
                                        alt={i.name}
                                        className="size-14 rounded object-cover"
                                        loading="lazy"
                                    />
                                ) : (
                                    'No photo'
                                )}
                            </td>
                            <td>
                                <Link
                                    className="font-medium underline"
                                    href={'/inventory/' + i.id}
                                >
                                    {i.name}
                                </Link>
                                <p className="text-muted-foreground">
                                    {i.reference_code}
                                </p>
                            </td>
                            <td>{i.category.name}</td>
                            <td>{modes[i.tracking_mode]}</td>
                            <td>{i.unit_of_measure}</td>
                            <td>
                                <StatusBadge active={i.is_active} />
                            </td>
                        </tr>
                    ))}
                </DataTable>
            ) : (
                <EmptyState>
                    No matching items. Employees see items associated with their
                    currently assigned storage area.
                </EmptyState>
            )}
            <Pagination page={items} />
        </OrganizationPage>
    );
}
Inventory.layout = {
    breadcrumbs: [{ title: 'Inventory', href: '/inventory' }],
};
