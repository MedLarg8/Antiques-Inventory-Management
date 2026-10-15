<?php

namespace App\Models;

use Database\Factories\StorageAreaAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['storage_area_id', 'user_id', 'assigned_at', 'ended_at'])]
class StorageAreaAssignment extends Model
{
    /** @use HasFactory<StorageAreaAssignmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['assigned_at' => 'immutable_datetime', 'ended_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<StorageArea, $this> */
    public function storageArea(): BelongsTo
    {
        return $this->belongsTo(StorageArea::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
