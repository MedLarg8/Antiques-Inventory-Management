<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\StorageArea;
use App\Models\StorageAreaAssignment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeStorageAreaAssignment
{
    public function handle(StorageArea $storageArea, ?int $userId, ?int $expectedAssignmentId): void
    {
        try {
            DB::transaction(function () use ($storageArea, $userId, $expectedAssignmentId): void {
                // All assignment writers lock the target user, then the area.
                $user = $userId === null ? null : User::query()->lockForUpdate()->find($userId);
                $area = StorageArea::query()->lockForUpdate()->findOrFail($storageArea->id);
                $current = $area->currentAssignment()->first();

                if ($current?->id !== $expectedAssignmentId) {
                    throw ValidationException::withMessages(['assignment' => 'Responsibility changed since this page was opened. Refresh the page and try again.']);
                }
                if ($userId !== null) {
                    if (! $user || $user->role !== Role::StockEmployee) {
                        throw ValidationException::withMessages(['user_id' => 'Select a stock employee account.']);
                    }
                    if (! $area->is_active) {
                        throw ValidationException::withMessages(['user_id' => 'Inactive storage areas cannot receive new assignments.']);
                    }
                    if (StorageAreaAssignment::where('user_id', $userId)->whereNull('ended_at')->where('storage_area_id', '!=', $area->id)->exists()) {
                        throw ValidationException::withMessages(['user_id' => 'This employee already manages another storage area. End that assignment first.']);
                    }
                }
                if ($current?->user_id === $userId) {
                    return;
                }
                $now = now();
                if ($current) {
                    $current->update(['ended_at' => $now]);
                }
                if ($userId !== null) {
                    $area->assignments()->create(['user_id' => $userId, 'assigned_at' => $now]);
                }
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['assignment' => 'An assignment changed concurrently. Refresh the page before trying again.']);
        }
    }
}
