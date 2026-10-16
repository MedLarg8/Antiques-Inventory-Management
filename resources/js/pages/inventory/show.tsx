import { Link, useForm } from '@inertiajs/react';
import InventoryImages from '@/components/inventory-images';
import InventoryBalances from '@/components/inventory-balances';
import {
    OrganizationPage,
    DataTable,
    EmptyState,
    Pagination,
    StatusBadge,
    selectClass,
} from '@/components/organization';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import InputError from '@/components/input-error';
import type { Item, Photo, Balances, Asset } from '@/types/inventory';
import { modes } from '@/types/inventory';
import type { Paginated, StorageArea } from '@/types/organization';
export default function ItemShow({
    item,
    images,
    balances,
    assets,
    areas,
    canManage,
}: {
    item: Item;
    images: Photo[];
    balances: Balances;
    assets: Paginated<Asset> | null;
    areas: StorageArea[];
    canManage: boolean;
}) {
    const form = useForm({ storage_area_id: '', minimum_quantity: '' });
    return (
        <OrganizationPage
            title={item.name}
            description={item.reference_code}
            actions={
                canManage && (
                    <Button asChild>
                        <Link href={'/catalog/items/' + item.id + '/edit'}>
                            Edit item
                        </Link>
                    </Button>
                )
            }
        >
            <dl className="grid gap-5 rounded border p-6 sm:grid-cols-3">
                {Object.entries({
                    Category: item.category.name,
                    Tracking: modes[item.tracking_mode],
                    Unit: item.unit_of_measure,
                    Brand: item.brand,
                    Model: item.model,
                    Description: item.description,
                    Notes: item.notes,
                }).map(([k, v]) => (
                    <div key={k}>
                        <dt className="text-sm text-muted-foreground">{k}</dt>
                        <dd className="whitespace-pre-wrap">
                            {v || 'Not provided'}
                        </dd>
                    </div>
                ))}
                <div>
                    <dt className="text-sm text-muted-foreground">Status</dt>
                    <dd>
                        <StatusBadge active={item.is_active} />
                    </dd>
                </div>
            </dl>
            <InventoryImages
                images={images}
                kind="items"
                record={item.id}
                canManage={canManage}
            />
            {item.tracking_mode === 'individual_asset' ? (
                <section className="space-y-4">
                    <h2 className="text-lg font-semibold">Registered assets</h2>
                    <p className="text-sm text-muted-foreground">
                        Catalog registrations do not confirm physical location
                        or availability. Physical stock is unavailable until
                        authorized asset-location operations exist.
                    </p>
                    {canManage && (
                        <Button asChild>
                            <Link
                                href={'/catalog/assets/create?item=' + item.id}
                            >
                                Register asset
                            </Link>
                        </Button>
                    )}
                    {assets && assets.data.length ? (
                        <>
                            <DataTable
                                headers={['Asset reference', 'Serial number']}
                            >
                                {assets.data.map((a) => (
                                    <tr key={a.id}>
                                        <td>
                                            <Link
                                                className="underline"
                                                href={'/assets/' + a.id}
                                            >
                                                {a.asset_reference}
                                            </Link>
                                        </td>
                                        <td>
                                            {a.serial_number ?? 'Not provided'}
                                        </td>
                                    </tr>
                                ))}
                            </DataTable>
                            <Pagination page={assets} />
                        </>
                    ) : (
                        <EmptyState>No asset records.</EmptyState>
                    )}
                </section>
            ) : (
                <>
                    <InventoryBalances
                        balances={balances}
                        unit={item.unit_of_measure}
                    />
                    {canManage && (
                        <form
                            className="space-y-4 rounded border p-5"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.put(
                                    '/inventory/' + item.id + '/threshold',
                                    { preserveScroll: true },
                                );
                            }}
                        >
                            <h2 className="font-semibold">
                                Configure storage-area threshold
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Creates an uninitialized balance record if
                                needed. Existing quantities are preserved. Leave
                                minimum blank to disable the threshold.
                            </p>
                            <label className="block">
                                Storage area
                                <select
                                    className={selectClass}
                                    value={form.data.storage_area_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'storage_area_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        Choose storage area
                                    </option>
                                    {areas.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.city.name} / {a.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="block">
                                Minimum quantity ({item.unit_of_measure})
                                <Input
                                    inputMode="decimal"
                                    value={form.data.minimum_quantity}
                                    onChange={(e) =>
                                        form.setData(
                                            'minimum_quantity',
                                            e.target.value,
                                        )
                                    }
                                />
                            </label>
                            {Object.values(form.errors).map((e) => (
                                <InputError key={e} message={e} />
                            ))}
                            <Button disabled={form.processing}>
                                Save threshold
                            </Button>
                        </form>
                    )}
                </>
            )}
        </OrganizationPage>
    );
}
ItemShow.layout = {
    breadcrumbs: [
        { title: 'Inventory', href: '/inventory' },
        { title: 'Item details', href: '#' },
    ],
};
