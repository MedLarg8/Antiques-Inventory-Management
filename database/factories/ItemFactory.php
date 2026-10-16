<?php

namespace Database\Factories;

use App\Enums\TrackingMode;
use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Item> */
class ItemFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->words(3, true), 'reference_code' => fake()->unique()->bothify('ITEM-????####'), 'category_id' => ItemCategory::factory(), 'tracking_mode' => TrackingMode::Consumable, 'unit_of_measure' => 'piece', 'is_active' => true];
    }
}
