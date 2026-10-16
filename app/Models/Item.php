<?php

namespace App\Models;

use App\Enums\TrackingMode;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** @property TrackingMode $tracking_mode */
#[Fillable(['reference_code', 'name', 'category_id', 'tracking_mode', 'unit_of_measure', 'description', 'brand', 'model', 'notes', 'is_active'])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['tracking_mode' => TrackingMode::class, 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<ItemCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    /** @return HasMany<ItemAsset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(ItemAsset::class);
    }

    /** @return HasMany<StockRequestItem, $this> */
    public function stockRequestItems(): HasMany
    {
        return $this->hasMany(StockRequestItem::class);
    }

    /** @return HasMany<StorageAreaInventory, $this> */
    public function balances(): HasMany
    {
        return $this->hasMany(StorageAreaInventory::class);
    }

    /** @return HasMany<ItemImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ItemImage::class)->orderBy('display_order')->orderBy('id');
    }

    /** @return HasOne<ItemImage, $this> */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ItemImage::class)->where('is_primary', true);
    }

    /** @param Builder<Item> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('access-administration')) {
            $query->whereHas('balances', fn ($q) => $q->visibleTo($user));
        }
    }
}
