<?php

namespace App\Models;

use Database\Factories\ItemAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['asset_reference', 'serial_number', 'condition_notes', 'description'])]
class ItemAsset extends Model
{
    /** @use HasFactory<ItemAssetFactory> */
    use HasFactory;

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** @return HasMany<ItemImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ItemImage::class)->orderBy('display_order')->orderBy('id');
    }
}
