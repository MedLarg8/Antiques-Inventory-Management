<?php

namespace App\Policies;

use App\Enums\StockRequestStatus;
use App\Models\StockRequest;
use App\Models\StorageArea;
use App\Models\User;

class StockRequestPolicy
{
    public function create(User $user): bool
    {
        return $user->can('view-own-storage') && $user->currentStorageArea()->where('storage_areas.is_active', true)->exists();
    }

    public function view(User $user, StockRequest $request): bool
    {
        return $user->is_active && StockRequest::visibleTo($user)->whereKey($request->id)->exists();
    }

    public function update(User $user, StockRequest $request): bool
    {
        return $request->requested_by === $user->id && $request->status === StockRequestStatus::Draft && $this->responsible($user, $request);
    }

    public function cancel(User $user, StockRequest $request): bool
    {
        return $user->can('view-own-storage') && $request->requested_by === $user->id && $request->status === StockRequestStatus::Draft;
    }

    public function review(User $user, StockRequest $request): bool
    {
        return $user->can('access-administration') && $request->requested_by !== $user->id && $request->status === StockRequestStatus::Pending;
    }

    public function execute(User $user, StockRequest $request): bool
    {
        return $request->status === StockRequestStatus::Approved && $this->responsible($user, $request);
    }

    public function responsible(User $user, StockRequest $request): bool
    {
        return $user->can('view-own-storage') && StorageArea::whereKey($request->storage_area_id)->where('is_active', true)->whereHas('currentAssignment', fn ($q) => $q->where('user_id', $user->id))->exists();
    }
}
