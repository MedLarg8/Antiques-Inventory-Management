<?php

namespace App\Models;

use App\Enums\StockOperation;
use App\Enums\StockRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property StockOperation $operation_type
 * @property StockRequestStatus $status
 */
class StockRequest extends Model
{
    protected $guarded = ['*'];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['operation_type' => StockOperation::class, 'status' => StockRequestStatus::class, 'submitted_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime', 'executed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<StorageArea,$this> */
    public function storageArea(): BelongsTo
    {
        return $this->belongsTo(StorageArea::class);
    }

    /** @return BelongsTo<User,$this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User,$this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<User,$this> */
    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    /** @return HasMany<StockRequestItem,$this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockRequestItem::class)->orderBy('item_id');
    }

    /** @return HasMany<StockRequestEvent,$this> */
    public function events(): HasMany
    {
        return $this->hasMany(StockRequestEvent::class)->orderBy('id');
    }

    /** @return HasMany<StockMovement,$this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** @param Builder<StockRequest> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('access-administration')) {
            $query->where(fn ($q) => $q->where('requested_by', $user->id)->orWhere('executed_by', $user->id)->orWhere(fn ($q) => $q->where('status', StockRequestStatus::Approved)->whereIn('storage_area_id', StorageAreaAssignment::where('user_id', $user->id)->whereNull('ended_at')->select('storage_area_id'))));
        }
    }
}
