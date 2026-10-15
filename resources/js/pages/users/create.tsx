import { Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { OrganizationPage } from '@/components/organization';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function CreateEmployee() {
    const form = useForm({ name: '', email: '' });
    return (
        <OrganizationPage
            title="Create stock employee"
            description="The employee will choose their own password through a secure setup link. No password is displayed or shared with administrators."
        >
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/users');
                }}
                className="max-w-xl space-y-6 rounded-xl border p-6"
            >
                <div className="space-y-2">
                    <Label htmlFor="name">Full name</Label>
                    <Input
                        id="name"
                        name="name"
                        required
                        maxLength={255}
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                    />
                    <InputError message={form.errors.name} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="email">Work email</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        required
                        maxLength={255}
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                    />
                    <InputError message={form.errors.email} />
                </div>
                <p className="text-sm leading-6 text-muted-foreground">
                    Role: Stock Employee. After setting a password, the employee
                    must verify their email to access the workspace. Storage
                    responsibility is assigned separately.
                </p>
                <div className="flex gap-3">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing
                            ? 'Creating…'
                            : 'Create and send setup link'}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href="/users">Cancel</Link>
                    </Button>
                </div>
            </form>
        </OrganizationPage>
    );
}
CreateEmployee.layout = {
    breadcrumbs: [
        { title: 'User Management', href: '/users' },
        { title: 'Create employee', href: '/users/create' },
    ],
};
