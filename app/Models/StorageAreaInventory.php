<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\StorageAreaInventoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property numeric-string $quantity
 * @property numeric-string|null $minimum_quantity
 * @property CarbonImmutable|null $initialized_at
 */
#[Fillable(['minimum_quantity'])]
class StorageAreaInventory extends Model
{
    /** @use HasFactory<StorageAreaInventoryFactory> */
    use HasFactory;

    protected $table = 'storage_area_inventory';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'minimum_quantity' => 'decimal:3', 'initialized_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** @return BelongsTo<StorageArea, $this> */
    public function storageArea(): BelongsTo
    {
        return $this->belongsTo(StorageArea::class);
    }

    /** @param Builder<StorageAreaInventory> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('access-administration')) {
            $query->whereHas('storageArea.currentAssignment', fn ($q) => $q->where('user_id', $user->id));
        }
    }

    /** @param Builder<StorageAreaInventory> $query */
    public function scopeLowStock(Builder $query): void
    {
        $query->whereNotNull('initialized_at')->whereNotNull('minimum_quantity')->whereColumn('quantity', '<', 'minimum_quantity');
    }

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return [
            'id' => $this->id, 'item_id' => $this->item_id, 'storage_area_id' => $this->storage_area_id,
            'quantity' => $this->initialized_at ? $this->quantity : null,
            'minimum_quantity' => $this->minimum_quantity, 'initialized_at' => $this->initialized_at?->toIso8601String(),
            'status' => ! $this->initialized_at ? 'uninitialized' : ($this->minimum_quantity !== null && bccomp($this->quantity, $this->minimum_quantity, 3) < 0 ? 'low' : 'recorded'),
            'item' => $this->relationLoaded('item') ? $this->item : null,
            'storage_area' => $this->relationLoaded('storageArea') ? $this->storageArea : null,
        ];
    }
}
