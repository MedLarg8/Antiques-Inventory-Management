import OrganizationForm from '@/components/organization-form';
import type { City } from '@/types/organization';
export default function CityForm({ city }: { city: City | null }) {
    return <OrganizationForm kind="city" record={city} />;
}
CityForm.layout = {
    breadcrumbs: [
        { title: 'Cities', href: '/cities' },
        { title: 'City details', href: '#' },
    ],
};
