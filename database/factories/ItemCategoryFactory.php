<?php

namespace Database\Factories;

use App\Models\ItemCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ItemCategory> */
class ItemCategoryFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->word(), 'code' => fake()->unique()->bothify('CAT-????####'), 'is_active' => true];
    }
}
