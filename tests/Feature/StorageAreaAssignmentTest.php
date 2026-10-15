<?php

namespace Tests\Feature;

use App\Actions\ChangeStorageAreaAssignment;
use App\Enums\Role;
use App\Models\StorageArea;
use App\Models\StorageAreaAssignment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorageAreaAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_relationships_reassignment_and_unassignment_retain_history(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $first = User::factory()->create();
        $second = User::factory()->create();
        $area = StorageArea::factory()->create();
        $url = '/storage-areas/'.$area->id.'/assignment';
        $this->actingAs($admin)->put($url, ['user_id' => $first->id, 'expected_assignment_id' => null])->assertSessionHasNoErrors();
        $original = $area->currentAssignment()->sole();
        $this->assertTrue($area->currentResponsibleEmployee->is($first));
        $this->assertTrue($first->currentStorageArea->is($area));
        $this->assertTrue($area->city->storageAreas->first()->is($area));
        $this->travel(1)->hour();
        $this->put($url, ['user_id' => $second->id, 'expected_assignment_id' => $original->id])->assertSessionHasNoErrors();
        $this->assertNotNull($original->fresh()->ended_at);
        $this->assertDatabaseCount('storage_area_assignments', 2);
        $this->assertNull($first->fresh()->currentStorageArea);
        $current = $area->fresh()->currentAssignment;
        $this->assertTrue($second->fresh()->currentStorageArea->is($area));
        $this->assertTrue($current->assigned_at->equalTo($original->fresh()->ended_at));
        $this->put($url, ['user_id' => null, 'expected_assignment_id' => $current->id])->assertSessionHasNoErrors();
        $this->assertNull($area->fresh()->currentAssignment);
        $this->assertSame(2, $area->assignments()->whereNotNull('ended_at')->count());
        $this->get('/storage-areas/'.$area->id)->assertInertia(fn (Assert $page) => $page->has('history.data', 2)->where('area.current_assignment', null));
    }

    public function test_invalid_employee_inactive_area_and_already_assigned_employee_are_rejected_atomically(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $existing = StorageAreaAssignment::factory()->create();
        $occupied = StorageAreaAssignment::factory()->create();
        $url = '/storage-areas/'.$existing->storage_area_id.'/assignment';
        $this->actingAs($admin)->put($url, ['user_id' => $occupied->user_id, 'expected_assignment_id' => $existing->id])->assertSessionHasErrors('user_id');
        $this->put($url, ['user_id' => $admin->id, 'expected_assignment_id' => $existing->id])->assertSessionHasErrors('user_id');
        $this->put($url, ['user_id' => 999999, 'expected_assignment_id' => $existing->id])->assertSessionHasErrors('user_id');
        $existing->storageArea->update(['is_active' => false]);
        $this->put($url, ['user_id' => User::factory()->create()->id, 'expected_assignment_id' => $existing->id])->assertSessionHasErrors('user_id');
        $this->assertNull($existing->fresh()->ended_at);
        $this->assertNull($occupied->fresh()->ended_at);
        $this->assertDatabaseCount('storage_area_assignments', 2);
        $this->put($url, ['user_id' => null, 'expected_assignment_id' => $existing->id])->assertSessionHasNoErrors();
        $this->assertNotNull($existing->fresh()->ended_at);
    }

    public function test_stale_pages_cannot_overwrite_new_responsibility(): void
    {
        $existing = StorageAreaAssignment::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::Admin]))
            ->put('/storage-areas/'.$existing->storage_area_id.'/assignment', ['user_id' => User::factory()->create()->id, 'expected_assignment_id' => null])
            ->assertSessionHasErrors('assignment');
        $this->assertDatabaseCount('storage_area_assignments', 1);
        $this->put('/storage-areas/'.$existing->storage_area_id.'/assignment', ['user_id' => null])->assertSessionHasErrors('expected_assignment_id');
    }

    public function test_database_partial_indexes_prevent_both_forms_of_overlap(): void
    {
        $assignment = StorageAreaAssignment::factory()->create();
        $otherArea = StorageArea::factory()->create();
        $otherEmployee = User::factory()->create();
        foreach ([
            ['storage_area_id' => $assignment->storage_area_id, 'user_id' => $otherEmployee->id],
            ['storage_area_id' => $otherArea->id, 'user_id' => $assignment->user_id],
        ] as $attributes) {
            try {
                DB::transaction(fn () => StorageAreaAssignment::create([...$attributes, 'assigned_at' => now()]));
                $this->fail('Partial unique index should reject overlapping active assignments.');
            } catch (UniqueConstraintViolationException) {
                $this->assertSame(1, StorageAreaAssignment::count());
            }
        }
    }

    public function test_employee_can_only_view_currently_assigned_area_and_no_history_or_other_employees_leaks(): void
    {
        $assignment = StorageAreaAssignment::factory()->create();
        $other = StorageAreaAssignment::factory()->create();
        $this->actingAs($assignment->user)->get('/my-storage-area')->assertOk()->assertInertia(fn (Assert $page) => $page->component('storage-areas/mine')->where('assignment.id', $assignment->id)->missing('employees')->missing('history'));
        $this->get('/my-storage-area?user_id='.$other->user_id)->assertInertia(fn (Assert $page) => $page->where('assignment.id', $assignment->id));
        $this->get('/storage-areas/'.$other->storage_area_id)->assertForbidden();
        $this->get('/storage-areas/'.$assignment->storage_area_id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('history', null)->has('employees', 0)->where('canManage', false));
        app(ChangeStorageAreaAssignment::class)->handle($assignment->storageArea, null, $assignment->id);
        $this->get('/storage-areas/'.$assignment->storage_area_id)->assertForbidden();
        $this->get('/my-storage-area')->assertInertia(fn (Assert $page) => $page->where('assignment', null));
    }

    public function test_account_deletion_cannot_remove_assignment_history(): void
    {
        $assignment = StorageAreaAssignment::factory()->create(['ended_at' => now()]);
        $this->actingAs($assignment->user)->delete('/settings/profile', ['password' => 'password'])->assertSessionHasErrors('password');
        $this->assertAuthenticatedAs($assignment->user);
        $this->assertDatabaseHas('users', ['id' => $assignment->user_id]);
        $this->assertDatabaseCount('storage_area_assignments', 1);
        try {
            DB::transaction(fn () => $assignment->user->delete());
            $this->fail('Foreign key must protect assignment history.');
        } catch (QueryException) {
            $this->assertDatabaseCount('storage_area_assignments', 1);
        }
    }

    public function test_city_and_storage_area_foreign_keys_prevent_orphaned_records(): void
    {
        $assignment = StorageAreaAssignment::factory()->create();
        foreach ([$assignment->storageArea, $assignment->storageArea->city] as $model) {
            try {
                DB::transaction(fn () => $model->delete());
                $this->fail('Referenced organizational records must not be deleted.');
            } catch (QueryException) {
                $this->assertDatabaseCount('cities', 1);
                $this->assertDatabaseCount('storage_areas', 1);
            }
        }
    }
}
