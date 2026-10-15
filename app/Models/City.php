<?php

namespace App\Models;

use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'description', 'is_active'])]
class City extends Model
{
    /** @use HasFactory<CityFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<StorageArea, $this> */
    public function storageAreas(): HasMany
    {
        return $this->hasMany(StorageArea::class);
    }
}
