<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['quantity_delta' => 'decimal:3', 'quantity_before' => 'decimal:3', 'quantity_after' => 'decimal:3', 'executed_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<StockRequest,$this> */
    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class);
    }

    /** @return BelongsTo<StockRequestItem,$this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(StockRequestItem::class, 'stock_request_item_id');
    }

    /** @return BelongsTo<StorageArea,$this> */
    public function storageArea(): BelongsTo
    {
        return $this->belongsTo(StorageArea::class);
    }

    /** @return BelongsTo<User,$this> */
    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    /** @param Builder<StockMovement> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('access-administration')) {
            $query->whereHas('storageArea.currentAssignment', fn ($q) => $q->where('user_id', $user->id));
        }
    }
}
