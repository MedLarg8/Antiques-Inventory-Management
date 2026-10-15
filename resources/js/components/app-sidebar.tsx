import { Link, usePage } from '@inertiajs/react';
import {
    Archive,
    ArrowLeftRight,
    Building2,
    ChartNoAxesCombined,
    ClipboardCheck,
    FileText,
    LayoutGrid,
    Settings2,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

const items: NavItem[] = [
    { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
    { title: 'Inventory', href: '/inventory', icon: Archive },
    { title: 'Storage Areas', href: '/storage-areas', icon: Building2 },
    {
        title: 'Stock Movements',
        href: '/stock-movements',
        icon: ArrowLeftRight,
    },
    {
        title: 'Approval Requests',
        href: '/approval-requests',
        icon: ClipboardCheck,
    },
    { title: 'Documents', href: '/documents', icon: FileText },
    { title: 'Reports', href: '/reports', icon: ChartNoAxesCombined },
];

export function AppSidebar() {
    const { auth } = usePage().props;
    const navigation =
        auth.user.role === 'admin'
            ? [
                  ...items,
                  {
                      title: 'Administration',
                      href: '/administration',
                      icon: Settings2,
                  },
              ]
            : items;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                <NavMain items={navigation} />
            </SidebarContent>
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
