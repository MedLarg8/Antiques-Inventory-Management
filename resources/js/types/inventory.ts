import type { Paginated, StorageArea } from './organization';
export type Category = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    parent_id: number | null;
    parent?: Category | null;
    is_active: boolean;
};
export type Item = {
    id: number;
    name: string;
    reference_code: string;
    category_id: number;
    category: Category;
    tracking_mode: string;
    unit_of_measure: string;
    description: string | null;
    brand: string | null;
    model: string | null;
    notes: string | null;
    is_active: boolean;
    thumbnail?: string | null;
};
export type Asset = {
    id: number;
    item_id: number;
    item: Item;
    asset_reference: string;
    serial_number: string | null;
    description: string | null;
    condition_notes: string | null;
};
export type Photo = {
    id: number;
    caption: string | null;
    is_primary: boolean;
    display_order: number;
    thumbnail_url: string;
    preview_url: string;
    original_url: string;
};
export type Balance = {
    id: number;
    quantity: string | null;
    minimum_quantity: string | null;
    status: 'uninitialized' | 'low' | 'recorded';
    item: Item | null;
    storage_area: StorageArea | null;
};
export type Balances = Paginated<Balance>;
export const modes: Record<string, string> = {
    quantity_consumable: 'Quantity consumable',
    quantity_reusable: 'Quantity reusable',
    individual_asset: 'Individual asset',
};
