import { Link } from '@inertiajs/react';
import InventoryBalances from '@/components/inventory-balances';
import { OrganizationPage } from '@/components/organization';
import type { Balances } from '@/types/inventory';
import type { StorageArea } from '@/types/organization';
export default function Area({
    area,
    balances,
}: {
    area: StorageArea;
    balances: Balances;
}) {
    return (
        <OrganizationPage
            title={area.name + ' inventory'}
            description={area.city.name}
            actions={
                <Link className="underline" href={'/storage-areas/' + area.id}>
                    Storage details
                </Link>
            }
        >
            <InventoryBalances balances={balances} />
        </OrganizationPage>
    );
}
Area.layout = { breadcrumbs: [{ title: 'Storage inventory', href: '#' }] };
