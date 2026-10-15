<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\StorageArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StorageArea> */
class StorageAreaFactory extends Factory
{
    public function definition(): array
    {
        return ['city_id' => City::factory(), 'name' => 'Storage '.fake()->unique()->word(), 'code' => fake()->unique()->bothify('AREA-####??'), 'location_details' => fake()->address(), 'is_active' => true];
    }
}
