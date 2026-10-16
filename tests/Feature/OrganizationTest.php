<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\City;
use App\Models\StorageArea;
use App\Models\StorageAreaAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin]);
    }

    public function test_admin_can_create_edit_and_deactivate_cities_without_deleting_areas(): void
    {
        $this->actingAs($this->admin())->post('/cities', ['name' => 'City Alpha', 'code' => 'alpha', 'is_active' => true])
            ->assertRedirect('/cities')->assertSessionHasNoErrors();
        $city = City::sole();
        $this->assertSame('ALPHA', $city->code);
        $area = StorageArea::factory()->create(['city_id' => $city->id]);
        $this->put('/cities/'.$city->id, ['name' => 'City Updated', 'code' => 'ALPHA', 'description' => 'Office location', 'is_active' => false])
            ->assertRedirect('/cities')->assertSessionHasNoErrors();
        $this->assertFalse($city->fresh()->is_active);
        $this->assertSame('City Updated', $area->city->name);
        $this->assertTrue($area->fresh()->is_active);
        $this->delete('/cities/'.$city->id)->assertMethodNotAllowed();
        $this->assertDatabaseCount('cities', 1);
    }

    public function test_city_codes_are_unique_and_optional_codes_allow_multiple_nulls(): void
    {
        City::factory()->create(['code' => 'CITY-A']);
        $this->actingAs($this->admin())->post('/cities', ['name' => 'Duplicate', 'code' => 'city-a', 'is_active' => true])
            ->assertSessionHasErrors('code');
        foreach (['One', 'Two'] as $name) {
            $this->post('/cities', ['name' => $name, 'code' => '', 'is_active' => true])->assertSessionHasNoErrors();
        }
        $this->assertSame(2, City::whereNull('code')->count());
        $this->post('/cities', ['name' => '', 'code' => str_repeat('A', 31), 'is_active' => 'invalid'])->assertSessionHasErrors(['name', 'code', 'is_active']);
    }

    public function test_admin_can_manage_storage_areas_and_validates_city_and_unique_code(): void
    {
        $city = City::factory()->create();
        $payload = ['city_id' => $city->id, 'name' => 'Main Store', 'code' => 'store-1', 'is_active' => true, 'location_details' => 'Building A'];
        $this->actingAs($this->admin())->post('/storage-areas', $payload)->assertSessionHasNoErrors()->assertRedirect();
        $area = StorageArea::sole();
        $this->assertTrue($area->city->is($city));
        $this->assertSame('STORE-1', $area->code);
        $this->post('/storage-areas', $payload)->assertSessionHasErrors('code');
        $this->post('/storage-areas', [...$payload, 'code' => 'STORE-2', 'city_id' => 999999])->assertSessionHasErrors('city_id');
        $assignment = StorageAreaAssignment::factory()->create(['storage_area_id' => $area->id]);
        $this->put('/storage-areas/'.$area->id, [...$payload, 'is_active' => false])->assertSessionHasNoErrors();
        $this->assertFalse($area->fresh()->is_active);
        $this->assertNull($assignment->fresh()->ended_at);
        $this->delete('/storage-areas/'.$area->id)->assertMethodNotAllowed();
    }

    public function test_search_filters_and_management_pages_return_real_records(): void
    {
        $city = City::factory()->create(['name' => 'Northern City', 'code' => 'NORTH']);
        $other = City::factory()->create(['name' => 'Southern City', 'code' => 'SOUTH']);
        $area = StorageArea::factory()->create(['city_id' => $city->id, 'name' => 'Special Store', 'is_active' => false]);
        StorageArea::factory()->create(['city_id' => $other->id]);
        $this->actingAs($this->admin())->get('/cities?search=north')->assertInertia(fn (Assert $page) => $page->component('cities/index')->has('cities.data', 1)->where('cities.data.0.id', $city->id));
        $this->get('/storage-areas?city_id='.$city->id.'&status=inactive&search=special')
            ->assertInertia(fn (Assert $page) => $page->component('storage-areas/index')->has('areas.data', 1)->where('areas.data.0.id', $area->id));
        foreach (['/cities/create' => 'cities/form', '/cities/'.$city->id.'/edit' => 'cities/form',
            '/storage-areas/create' => 'storage-areas/form', '/storage-areas/'.$area->id.'/edit' => 'storage-areas/form',
            '/assignments' => 'assignments/index', '/users' => 'users/index', '/users/create' => 'users/create'] as $url => $component) {
            $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
        }
    }

    public function test_employees_cannot_access_or_modify_management_endpoints(): void
    {
        $city = City::factory()->create();
        $area = StorageArea::factory()->create(['city_id' => $city->id]);
        $employee = User::factory()->create();
        $this->actingAs($employee);
        foreach (['/cities', '/cities/create', '/cities/'.$city->id.'/edit', '/storage-areas', '/storage-areas/create', '/storage-areas/'.$area->id.'/edit', '/assignments', '/users', '/users/create'] as $url) {
            $this->get($url)->assertForbidden();
        }
        foreach (['/cities', '/storage-areas', '/users', '/users/'.$employee->id.'/invite'] as $url) {
            $this->post($url, [])->assertForbidden();
        }
        foreach (['/cities/'.$city->id, '/storage-areas/'.$area->id, '/storage-areas/'.$area->id.'/assignment'] as $url) {
            $this->put($url, [])->assertForbidden();
        }
        $this->assertDatabaseCount('cities', 1);
        $this->assertDatabaseCount('storage_area_assignments', 0);
    }

    public function test_guests_are_redirected_from_new_pages_and_writes(): void
    {
        foreach (['/cities', '/storage-areas', '/assignments', '/users', '/my-storage-area'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        foreach (['/cities', '/storage-areas', '/users'] as $url) {
            $this->post($url, [])->assertRedirect('/login');
        }
    }

    public function test_dashboard_metrics_are_database_derived_and_employee_props_are_scoped(): void
    {
        $this->actingAs($this->admin())->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('organization.cities', 0)->where('organization.storageAreas', 0));
        $assignment = StorageAreaAssignment::factory()->create();
        StorageArea::factory()->create(['city_id' => $assignment->storageArea->city_id, 'is_active' => false]);
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('organization.cities', 1)->where('organization.storageAreas', 2)->where('organization.activeStorageAreas', 1)
            ->where('organization.assignedStorageAreas', 1)->where('organization.unassignedStorageAreas', 1));
        $this->actingAs($assignment->user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('organization', null)->where('assignment.storage_area.id', $assignment->storage_area_id));
    }
}
