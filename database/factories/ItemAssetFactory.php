<?php

namespace Database\Factories;

use App\Enums\TrackingMode;
use App\Models\Item;
use App\Models\ItemAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ItemAsset> */
class ItemAssetFactory extends Factory
{
    public function definition(): array
    {
        return ['item_id' => Item::factory()->state(['tracking_mode' => TrackingMode::Individual]), 'asset_reference' => fake()->unique()->bothify('ASSET-????####')];
    }
}
