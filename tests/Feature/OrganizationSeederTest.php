<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\StorageAreaAssignment;
use Database\Seeders\OrganizationDevelopmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_fixture_seeder_is_local_only_and_idempotent(): void
    {
        $this->app->instance('env', 'local');
        $this->seed(OrganizationDevelopmentSeeder::class);
        City::first()->update(['name' => 'Locally edited']);
        $ended = StorageAreaAssignment::first();
        $ended->update(['ended_at' => now()]);
        $this->seed(OrganizationDevelopmentSeeder::class);
        $this->assertDatabaseCount('cities', 3);
        $this->assertDatabaseCount('storage_areas', 6);
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('storage_area_assignments', 3);
        $this->assertSame(2, StorageAreaAssignment::whereNull('ended_at')->count());
        $this->assertDatabaseHas('cities', ['name' => 'Locally edited']);
    }

    public function test_organization_fixtures_refuse_production(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(\RuntimeException::class);
        (new OrganizationDevelopmentSeeder)->run();
    }
}
