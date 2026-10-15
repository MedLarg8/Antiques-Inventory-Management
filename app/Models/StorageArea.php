<?php

namespace App\Models;

use Database\Factories\StorageAreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

#[Fillable(['city_id', 'name', 'code', 'description', 'location_details', 'is_active'])]
class StorageArea extends Model
{
    /** @use HasFactory<StorageAreaFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return HasMany<StorageAreaAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(StorageAreaAssignment::class);
    }

    /** @return HasOne<StorageAreaAssignment, $this> */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(StorageAreaAssignment::class)->whereNull('ended_at');
    }

    /** @return HasOneThrough<User, StorageAreaAssignment, $this> */
    public function currentResponsibleEmployee(): HasOneThrough
    {
        return $this->hasOneThrough(User::class, StorageAreaAssignment::class, 'storage_area_id', 'id', 'id', 'user_id')
            ->whereNull('storage_area_assignments.ended_at');
    }
}
