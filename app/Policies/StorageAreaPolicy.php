<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\StorageArea;
use App\Models\User;

class StorageAreaPolicy
{
    public function view(User $user, StorageArea $storageArea): bool
    {
        return match ($user->role) {
            Role::Admin => true,
            Role::StockEmployee => $storageArea->currentAssignment()->where('user_id', $user->id)->exists(),
        };
    }
}
