import { OrganizationPage, Pagination } from '@/components/organization';
import StockFilters from '@/components/stock-filters';
import StockMovementTable from '@/components/stock-movement-table';
import type { Movement } from '@/types/stock';
import type { Paginated } from '@/types/organization';
export default function Movements({
    movements,
    filters,
    canReview,
    areas,
    cities,
}: {
    movements: Paginated<Movement>;
    filters: Record<string, string>;
    canReview: boolean;
    areas: { id: number; name: string }[];
    cities: { id: number; name: string }[];
}) {
    return (
        <OrganizationPage
            title="Stock movement history"
            description="Permanent history of successfully executed approved entries and exits. Quantities retain their recorded units."
        >
            <StockFilters
                url="/stock-movements"
                filters={filters}
                admin={canReview}
                areas={areas}
                cities={cities}
            />
            <StockMovementTable movements={movements.data} />
            <Pagination page={movements} />
        </OrganizationPage>
    );
}
Movements.layout = {
    breadcrumbs: [{ title: 'Stock movements', href: '/stock-movements' }],
};
