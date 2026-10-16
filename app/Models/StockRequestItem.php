<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property numeric-string $quantity */
class StockRequestItem extends Model
{
    protected $guarded = ['*'];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    /** @return BelongsTo<Item,$this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** @return BelongsTo<StockRequest,$this> */
    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class);
    }
}
