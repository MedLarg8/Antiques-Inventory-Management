import { OrganizationPage } from '@/components/organization';
import EmployeeProfileForm from '@/components/employee-profile-form';
export default function Create({ roles }: { roles: Record<string, string> }) {
    return (
        <OrganizationPage
            title="Create employee"
            description="Create an account and arrange secure password setup."
        >
            <EmployeeProfileForm roles={roles} />
        </OrganizationPage>
    );
}
Create.layout = {
    breadcrumbs: [
        { title: 'Employee directory', href: '/users' },
        { title: 'Create employee', href: '/users/create' },
    ],
};
