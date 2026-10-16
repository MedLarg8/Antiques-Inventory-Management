import type { Paginated, StorageArea } from './organization';
export type StockLine = {
    id: number;
    item_id: number;
    quantity: string;
    item_reference_snapshot: string;
    item_name_snapshot: string;
    unit_snapshot: string;
};
export type StockRequest = {
    id: number;
    request_number: string;
    operation_type: 'entry' | 'exit';
    status: string;
    storage_area_id: number;
    requested_by: number;
    reason: string;
    source_or_destination: string | null;
    created_at: string;
    submitted_at: string | null;
    reviewed_at: string | null;
    executed_at: string | null;
    rejection_reason: string | null;
    storage_area: StorageArea;
    requester: { id: number; name: string };
    reviewer: { id: number; name: string } | null;
    executor: { id: number; name: string } | null;
    lines: StockLine[];
    lines_count: number;
};
export type StockEvent = {
    id: number;
    event_type: string;
    previous_status: string | null;
    new_status: string;
    created_at: string;
    comment: string | null;
    actor: { id: number; name: string };
};
export type StockAvailability = {
    quantity: string | null;
    status: string;
    minimum_quantity: string | null;
};
export type StockChoice = {
    id: number;
    name: string;
    reference_code: string;
    unit_of_measure: string;
    availability: StockAvailability | null;
};
export type Movement = {
    id: number;
    stock_request_id: number;
    movement_type: 'entry' | 'exit';
    quantity_delta: string;
    quantity_before: string;
    quantity_after: string;
    executed_at: string;
    stock_request: { id: number; request_number: string };
    line: StockLine;
    storage_area: StorageArea;
    executor: { id: number; name: string };
    canViewRequest?: boolean;
};
export type StockSummary = {
    metrics: { label: string; value: number; href: string }[];
    recentCompleted: {
        id: number;
        request_number: string;
        storage_area: { name: string };
        executed_at: string;
    }[];
    recentMovements: Movement[];
};
export type StockRequestPage = Paginated<StockRequest>;
