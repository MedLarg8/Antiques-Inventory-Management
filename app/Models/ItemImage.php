<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Hidden(['storage_disk', 'storage_path', 'thumbnail_path', 'preview_path', 'uploaded_by'])]
class ItemImage extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** @return BelongsTo<ItemAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(ItemAsset::class, 'item_asset_id');
    }

    public function owner(): Item|ItemAsset
    {
        return $this->item_id !== null ? $this->item : $this->asset;
    }

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return [
            'id' => $this->id, 'caption' => $this->caption, 'is_primary' => $this->is_primary, 'display_order' => $this->display_order,
            'thumbnail_url' => route('item-images.show', ['image' => $this->id, 'variant' => 'thumbnail']),
            'preview_url' => route('item-images.show', ['image' => $this->id, 'variant' => 'preview']),
            'original_url' => route('item-images.show', ['image' => $this->id, 'variant' => 'original']),
        ];
    }
}
