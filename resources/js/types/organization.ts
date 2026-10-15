export type Person = { id: number; name: string; email?: string };
export type City = {
    id: number;
    name: string;
    code: string | null;
    description: string | null;
    is_active: boolean;
    storage_areas_count?: number;
};
export type StorageArea = {
    id: number;
    city_id: number;
    name: string;
    code: string;
    description: string | null;
    location_details: string | null;
    is_active: boolean;
    city: City;
    current_assignment: Assignment | null;
};
export type Assignment = {
    id: number;
    user_id: number;
    storage_area_id: number;
    assigned_at: string;
    ended_at: string | null;
    user?: Person;
    storage_area: StorageArea;
};
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
export type OrganizationStats = {
    cities: number;
    storageAreas: number;
    activeStorageAreas: number;
    assignedStorageAreas: number;
    unassignedStorageAreas: number;
};
