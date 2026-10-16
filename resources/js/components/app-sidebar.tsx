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
    MapPin,
    Users,
    UserCheck,
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
    { title: 'Stock Requests', href: '/stock-requests', icon: ClipboardCheck },
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
    const navigation = auth.canAdministrate
        ? [
              ...items,
              { title: 'Cities', href: '/cities', icon: MapPin },
              {
                  title: 'Storage Areas',
                  href: '/storage-areas',
                  icon: Building2,
              },
              {
                  title: 'Employee Assignments',
                  href: '/assignments',
                  icon: UserCheck,
              },
              { title: 'User Management', href: '/users', icon: Users },
              {
                  title: 'Administration',
                  href: '/administration',
                  icon: Settings2,
              },
          ]
        : [
              items[0],
              {
                  title: 'My Storage Area',
                  href: '/my-storage-area',
                  icon: Building2,
              },
              ...items
                  .slice(1)
                  .filter((item) => item.href !== '/approval-requests'),
          ];

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
