import type { Assignment, StorageArea } from './organization';
export type Employee = {
    id: number;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
    email_verified_at: string | null;
    phone: string | null;
    administrative_notes: string | null;
    created_at: string;
    last_login_at: string | null;
    current_storage_area?: StorageArea | null;
};
export type Audit = {
    id: number;
    action: string;
    created_at: string;
    details: {
        role?: string;
        is_active?: boolean;
        fields?: string[];
        before?: { role: string; is_active: boolean };
        after?: { role: string; is_active: boolean };
        storage_area_id?: number;
        new_employee_id?: number | null;
        delivery?: string;
        email_verification_cleared?: boolean;
    };
    actor: { id: number; name: string };
};
export type EmployeeAssignment = Assignment & { storage_area: StorageArea };
