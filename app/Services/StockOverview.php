<?php

namespace App\Services;

use App\Enums\StockRequestStatus;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Models\User;

class StockOverview
{
    /** @return array<string,mixed> */
    public function forUser(User $user): array
    {
        $admin = $user->can('access-administration');
        $counts = ($admin ? StockRequest::query() : StockRequest::where('requested_by', $user->id))->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $metrics = [];
        foreach ([StockRequestStatus::Pending, StockRequestStatus::Approved, StockRequestStatus::Completed, StockRequestStatus::Rejected] as $status) {
            $metrics[] = ['label' => ($admin ? '' : 'My ').$status->label(), 'value' => (int) ($counts[$status->value] ?? 0), 'href' => ($admin ? '/approval-requests' : '/stock-requests').'?status='.$status->value.($admin ? '' : '&scope=mine')];
        }
        if (! $admin) {
            $metrics[] = ['label' => 'Approved operations awaiting execution at my depot', 'value' => StockRequest::where('status', StockRequestStatus::Approved)->whereHas('storageArea.currentAssignment', fn ($q) => $q->where('user_id', $user->id))->count(), 'href' => '/stock-requests?scope=execution'];
        }

        return ['metrics' => $metrics,
            'recentCompleted' => StockRequest::visibleTo($user)->where('status', StockRequestStatus::Completed)->with('storageArea:id,name')->latest('executed_at')->limit(5)->get(['id', 'request_number', 'storage_area_id', 'executed_at']),
            'recentMovements' => StockMovement::visibleTo($user)->with(['line', 'storageArea:id,name', 'executor:id,name', 'stockRequest:id,request_number'])->latest('id')->limit(5)->get(),
        ];
    }
}
