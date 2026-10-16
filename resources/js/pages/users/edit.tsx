import { OrganizationPage } from '@/components/organization';
import EmployeeProfileForm from '@/components/employee-profile-form';
import type { Employee } from '@/types/employees';
export default function Edit({ employee }: { employee: Employee }) {
    return (
        <OrganizationPage
            title={'Edit ' + employee.name}
            description="Update profile information and administrative notes."
        >
            <EmployeeProfileForm employee={employee} />
        </OrganizationPage>
    );
}
Edit.layout = {
    breadcrumbs: [
        { title: 'Employee directory', href: '/users' },
        { title: 'Edit profile', href: '#' },
    ],
};
