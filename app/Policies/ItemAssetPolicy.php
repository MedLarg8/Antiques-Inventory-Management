<?php

namespace App\Policies;

use App\Models\ItemAsset;
use App\Models\User;

class ItemAssetPolicy
{
    public function view(User $user, ItemAsset $asset): bool
    {
        // No authorized asset-location record exists in Batch 3.
        return $user->can('access-administration');
    }
}
