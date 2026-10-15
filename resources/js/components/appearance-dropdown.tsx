import { Monitor, Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/hooks/use-appearance';

export default function AppearanceToggleDropdown() {
    const { updateAppearance } = useAppearance();
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Choose color theme"
                >
                    <Sun className="size-4 dark:hidden" />
                    <Moon className="hidden size-4 dark:block" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem onClick={() => updateAppearance('light')}>
                    <Sun />
                    Light
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => updateAppearance('dark')}>
                    <Moon />
                    Dark
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => updateAppearance('system')}>
                    <Monitor />
                    System
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
