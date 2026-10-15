<?php

namespace Database\Factories;

use App\Models\StorageArea;
use App\Models\StorageAreaAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StorageAreaAssignment> */
class StorageAreaAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return ['storage_area_id' => StorageArea::factory(), 'user_id' => User::factory(), 'assigned_at' => now(), 'ended_at' => null];
    }
}
