import { Head } from '@inertiajs/react';
import { Construction } from 'lucide-react';

export default function ModulePlaceholder({ title }: { title: string }) {
    return (
        <>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-8 p-6 lg:p-8">
                <div>
                    <p className="mb-2 text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                        Inventory Bureau
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {title}
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        A dedicated workspace for your organization's{' '}
                        {title.toLowerCase()}.
                    </p>
                </div>
                <div className="flex min-h-72 flex-col items-center justify-center rounded-xl border border-dashed bg-muted/20 p-8 text-center">
                    <Construction
                        className="mb-4 size-8 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <h2 className="text-lg font-medium">
                        Planned for a later batch
                    </h2>
                    <p className="mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                        This module is not available yet. No records or
                        operations are connected to this page.
                    </p>
                </div>
            </div>
        </>
    );
}

ModulePlaceholder.layout = {
    breadcrumbs: [{ title: 'Workspace', href: '/dashboard' }],
};
