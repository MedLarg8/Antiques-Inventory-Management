import { Link } from '@inertiajs/react';
import InventoryImages from '@/components/inventory-images';
import { OrganizationPage } from '@/components/organization';
import { Button } from '@/components/ui/button';
import type { Asset, Photo } from '@/types/inventory';
export default function AssetShow({
    asset,
    images,
}: {
    asset: Asset;
    images: Photo[];
}) {
    return (
        <OrganizationPage
            title={asset.asset_reference}
            description="Individual asset catalog record. Physical location and availability have not been established."
            actions={
                <Button asChild>
                    <Link href={'/catalog/assets/' + asset.id + '/edit'}>
                        Edit asset
                    </Link>
                </Button>
            }
        >
            <Link className="underline" href={'/inventory/' + asset.item.id}>
                {asset.item.name}
            </Link>
            <dl className="grid gap-5 rounded border p-6 sm:grid-cols-3">
                {Object.entries({
                    'Serial number': asset.serial_number,
                    'Condition notes': asset.condition_notes,
                    Description: asset.description,
                }).map(([k, v]) => (
                    <div key={k}>
                        <dt className="text-sm text-muted-foreground">{k}</dt>
                        <dd className="whitespace-pre-wrap">
                            {v || 'Not provided'}
                        </dd>
                    </div>
                ))}
            </dl>
            <InventoryImages
                images={images}
                kind="assets"
                record={asset.id}
                canManage
            />
        </OrganizationPage>
    );
}
AssetShow.layout = {
    breadcrumbs: [
        { title: 'Inventory', href: '/inventory' },
        { title: 'Asset details', href: '#' },
    ],
};
