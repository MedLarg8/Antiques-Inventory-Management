import OrganizationForm from '@/components/organization-form';
import type { City, StorageArea } from '@/types/organization';
export default function StorageAreaForm({
    area,
    cities,
}: {
    area: StorageArea | null;
    cities: City[];
}) {
    return (
        <OrganizationForm kind="storage area" record={area} cities={cities} />
    );
}
StorageAreaForm.layout = {
    breadcrumbs: [
        { title: 'Storage Areas', href: '/storage-areas' },
        { title: 'Storage details', href: '#' },
    ],
};
