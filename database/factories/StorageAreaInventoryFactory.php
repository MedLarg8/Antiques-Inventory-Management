<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\StorageArea;
use App\Models\StorageAreaInventory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StorageAreaInventory> */
class StorageAreaInventoryFactory extends Factory
{
    public function definition(): array
    {
        return ['item_id' => Item::factory(), 'storage_area_id' => StorageArea::factory(), 'quantity' => '0.000', 'minimum_quantity' => null, 'initialized_at' => null];
    }
}
