<?php

namespace Tests\Feature;

use App\Actions\ChangeStorageAreaAssignment;
use App\Actions\ManageEmployee;
use App\Actions\StockWorkflow;
use App\Enums\Role;
use App\Enums\StockRequestStatus;
use App\Enums\TrackingMode;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Models\StorageArea;
use App\Models\StorageAreaAssignment;
use App\Models\StorageAreaInventory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StockWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin]);
    }

    private function data(array $lines = [], string $type = 'entry'): array
    {
        return ['operation_type' => $type, 'reason' => 'Authorized organizational stock operation', 'source_or_destination' => 'External reference', 'lines' => $lines];
    }

    private function draft(User $user, array $lines, string $type = 'entry'): StockRequest
    {
        return app(StockWorkflow::class)->create($user, $this->data($lines, $type));
    }

    private function balance(StorageAreaAssignment $assignment, Item $item, string $quantity = '10.000', bool $initialized = true): StorageAreaInventory
    {
        return StorageAreaInventory::factory()->create(['storage_area_id' => $assignment->storage_area_id, 'item_id' => $item->id, 'quantity' => $quantity, 'initialized_at' => $initialized ? now() : null]);
    }

    private function approve(StockRequest $request, User $employee, User $admin): void
    {
        app(StockWorkflow::class)->submit($employee, $request);
        app(StockWorkflow::class)->approve($admin, $request);
    }

    public function test_employee_creates_and_edits_drafts_without_changing_quantities(): void
    {
        $a = StorageAreaAssignment::factory()->create();
        $item = Item::factory()->create();
        $balance = $this->balance($a, $item);
        $this->actingAs($a->user)->post('/stock-requests', $this->data())->assertSessionHasNoErrors()->assertRedirect();
        $request = StockRequest::sole();
        $this->assertSame($a->storage_area_id, $request->storage_area_id);
        $this->assertSame($a->user_id, $request->requested_by);
        $this->put('/stock-requests/'.$request->id, $this->data([['item_id' => $item->id, 'quantity' => '2.125']], 'exit'))->assertSessionHasNoErrors();
        $this->assertSame('2.125', $request->lines()->sole()->quantity);
        $this->assertSame('10.000', $balance->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->get('/stock-requests/'.$request->id.'/edit')->assertOk();
        $this->put('/stock-requests/'.$request->id, $this->data([['item_id' => $item->id, 'quantity' => '2.125']], 'exit'))->assertSessionHasNoErrors();
        $this->assertSame(2, $request->events()->count(), 'Unchanged saves must not duplicate update events.');
        $this->put('/stock-requests/'.$request->id, $this->data())->assertSessionHasNoErrors();
        $this->assertSame(0, $request->lines()->count());
        $this->post('/stock-requests/'.$request->id.'/submit')->assertSessionHasErrors('operation');
    }

    public function test_creation_rejects_foreign_depots_admins_unassigned_and_inactive_accounts(): void
    {
        $a = StorageAreaAssignment::factory()->create();
        $other = StorageArea::factory()->create();
        $item = Item::factory()->create();
        $data = $this->data([['item_id' => $item->id, 'quantity' => '1']]);
        $this->actingAs($a->user)->post('/stock-requests', [...$data, 'storage_area_id' => $other->id])->assertSessionHasErrors('storage_area_id');
        $this->actingAs($this->admin())->post('/stock-requests', $data)->assertForbidden();
        $this->actingAs(User::factory()->create())->post('/stock-requests', $data)->assertForbidden();
        $this->actingAs(User::factory()->create(['is_active' => false]))->post('/stock-requests', $data)->assertRedirect('/login');
        $a->storageArea->update(['is_active' => false]);
        $this->actingAs($a->user)->post('/stock-requests', $data)->assertForbidden();
        $this->assertDatabaseCount('stock_requests', 0);
    }

    public static function invalidQuantities(): array
    {
        return ['zero' => ['0'], 'negative' => ['-1'], 'fractional precision' => ['1.0001'], 'scientific' => ['1e3'], 'overflow' => ['1000000000000000'], 'number instead of decimal string' => [1], 'invalid' => ['abc']];
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_quantities_are_rejected(mixed $quantity): void
    {
        $a = StorageAreaAssignment::factory()->create();
        $item = Item::factory()->create();
        $this->actingAs($a->user)->post('/stock-requests', $this->data([['item_id' => $item->id, 'quantity' => $quantity]]))->assertSessionHasErrors('lines.0.quantity');
        $this->assertDatabaseCount('stock_requests', 0);
    }

    public function test_duplicate_invalid_inactive_and_individual_items_are_rejected(): void
    {
        $a = StorageAreaAssignment::factory()->create();
        $item = Item::factory()->create();
        $this->actingAs($a->user);
        $line = ['item_id' => $item->id, 'quantity' => '1.001'];
        $this->post('/stock-requests', $this->data([$line, $line]))->assertSessionHasErrors('lines.0.item_id');
        $this->post('/stock-requests', $this->data([['item_id' => 999999, 'quantity' => '1']]))->assertSessionHasErrors('lines.0.item_id');
        foreach ([['is_active' => false], ['is_active' => true, 'tracking_mode' => TrackingMode::Individual]] as $state) {
            $item->update($state);
            $this->post('/stock-requests', $this->data([$line]))->assertSessionHasErrors('operation');
        }
        $this->post('/stock-requests', [...$this->data(), 'operation_type' => 'transfer'])->assertSessionHasErrors('operation_type');
        $this->assertDatabaseCount('stock_requests', 0);
    }

    public function test_submission_approval_and_immutable_review_never_change_stock(): void
    {
        $a = StorageAreaAssignment::factory()->create();
        $admin = $this->admin();
        $item = Item::factory()->create();
        $b = $this->balance($a, $item);
        $r = $this->draft($a->user, [['item_id' => $item->id, 'quantity' => '2.000']], 'exit');
        $this->actingAs($a->user)->post('/stock-requests/'.$r->id.'/submit')->assertSessionHasNoErrors();
        $this->assertSame(StockRequestStatus::Pending, $r->fresh()->status);
        $this->assertNotNull($r->fresh()->submitted_at);
        $this->post('/stock-requests/'.$r->id.'/submit')->assertSessionHasErrors('operation');
        $this->put('/stock-requests/'.$r->id, $this->data())->assertSessionHasErrors('operation');
        $this->post('/stock-requests/'.$r->id.'/approve')->assertForbidden();
        $this->post('/stock-requests/'.$r->id.'/execute', ['confirmed' => true])->assertSessionHasErrors('operation');
        $this->actingAs($admin)->post('/stock-requests/'.$r->id.'/approve')->assertSessionHasNoErrors();
        $this->assertSame($admin->id, $r->fresh()->reviewed_by);
        $this->assertSame(StockRequestStatus::Approved, $r->fresh()->status);
        $this->assertSame('10.000', $b->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->post('/stock-requests/'.$r->id.'/approve')->assertSessionHasErrors('operation');
        $this->post('/stock-requests/'.$r->id.'/reject', ['rejection_reason' => 'Late rejection'])->assertSessionHasErrors('operation');
        $this->assertDatabaseHas('stock_request_events', ['stock_request_id' => $r->id, 'actor_id' => $admin->id, 'event_type' => 'approved']);
        $this->actingAs($a->user)->put('/stock-requests/'.$r->id, $this->data())->assertSessionHasErrors('operation');
    }

    public function test_rejection_requires_reason_and_is_terminal_and_draft_only_cancellation(): void
    {
        $a = StorageAreaAssignment::factory()->create();
        $admin = $this->admin();
        $item = Item::factory()->create();
        $r = $this->draft($a->user, [['item_id' => $item->id, 'quantity' => '1']]);
        app(StockWorkflow::class)->submit($a->user, $r);
        $this->actingAs($admin)->post('/stock-requests/'.$r->id.'/reject')->assertSessionHasErrors('rejection_reason');
        $this->post('/stock-requests/'.$r->id.'/reject', ['rejection_reason' => 'Correct the quantities and submit a new request.'])->assertSessionHasNoErrors();
        $this->assertSame(StockRequestStatus::Rejected, $r->fresh()->status);
        $this->actingAs($a->user)->post('/stock-requests/'.$r->id.'/submit')->assertSessionHasErrors('operation');
        $this->post('/stock-requests/'.$r->id.'/execute', ['confirmed' => true])->assertSessionHasErrors('operation');
        $this->post('/stock-requests/'.$r->id.'/cancel')->assertSessionHasErrors('operation');
        $draft = $this->draft($a->user, []);
        $this->post('/stock-requests/'.$draft->id.'/cancel')->assertSessionHasNoErrors();
        $this->assertSame(StockRequestStatus::Cancelled, $draft->fresh()->status);
        $this->post('/stock-requests/'.$draft->id.'/submit')->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_submission_checks_active_items_current_assignment_and_exit_availability(): void
    {
        $a = StorageAreaAssignment::factory()->create();
        $item = Item::factory()->create();
        $b = $this->balance($a, $item, '1.000');
        $r = $this->draft($a->user, [['item_id' => $item->id, 'quantity' => '2']], 'exit');
        $this->actingAs($a->user)->post('/stock-requests/'.$r->id.'/submit')->assertSessionHasErrors('operation');
        $this->assertSame(StockRequestStatus::Draft, $r->fresh()->status);
        $b->forceFill(['quantity' => '3'])->save();
        $item->update(['is_active' => false]);
        $this->post('/stock-requests/'.$r->id.'/submit')->assertSessionHasErrors('operation');
        $item->update(['is_active' => true]);
        $a->storageArea->update(['is_active' => false]);
        $this->post('/stock-requests/'.$r->id.'/submit')->assertForbidden();
        $a->storageArea->update(['is_active' => true]);
        app(ChangeStorageAreaAssignment::class)->handle($a->storageArea, null, $a->id);
        $this->post('/stock-requests/'.$r->id.'/submit')->assertForbidden();
    }

    public function test_missing_and_uninitialized_balances_never_become_confirmed_stock(): void
    {
        $a = StorageAreaAssignment::factory()->create();
        $admin = $this->admin();
        $item = Item::factory()->create();
        $entry = $this->draft($a->user, [['item_id' => $item->id, 'quantity' => '1.001']]);
        $this->approve($entry, $a->user, $admin);
        $this->actingAs($a->user)->post('/stock-requests/'.$entry->id.'/execute', ['confirmed' => true])->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('storage_area_inventory', 0);
        $b = $this->balance($a, $item, '0.000', false);
        $this->post('/stock-requests/'.$entry->id.'/execute', ['confirmed' => true])->assertSessionHasErrors('operation');
        $exit = $this->draft($a->user, [['item_id' => $item->id, 'quantity' => '1']], 'exit');
        $this->post('/stock-requests/'.$exit->id.'/submit')->assertSessionHasErrors('operation');
        $this->assertNull($b->fresh()->initialized_at);
        $this->assertSame('0.000', $b->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_exact_multi_item_entry_and_exit_execution_create_one_movement_per_line(): void
    {
        $a = StorageAreaAssignment::factory()->create();
        $admin = $this->admin();
        $first = Item::factory()->create();
        $second = Item::factory()->create(['tracking_mode' => TrackingMode::Reusable]);
        $b1 = $this->balance($a, $first, '1.005');
        $b2 = $this->balance($a, $second, '10.000');
        $r = $this->draft($a->user, [['item_id' => $second->id, 'quantity' => '2.100'], ['item_id' => $first->id, 'quantity' => '0.125']]);
        $this->approve($r, $a->user, $admin);
        $this->actingAs($admin)->post('/stock-requests/'.$r->id.'/execute', ['confirmed' => true])->assertForbidden();
        $this->actingAs($a->user)->post('/stock-requests/'.$r->id.'/execute')->assertSessionHasErrors('confirmed');
        $this->post('/stock-requests/'.$r->id.'/execute', ['confirmed' => true, 'lines' => [['quantity' => '999']]])->assertSessionHasErrors('lines');
        $this->post('/stock-requests/'.$r->id.'/execute', ['confirmed' => true])->assertSessionHasNoErrors();
        $this->assertSame('1.130', $b1->fresh()->quantity);
        $this->assertSame('12.100', $b2->fresh()->quantity);
        $this->assertSame(StockRequestStatus::Completed, $r->fresh()->status);
        $this->assertSame($a->user_id, $r->fresh()->executed_by);
        $this->assertDatabaseCount('stock_movements', 2);
        $m = StockMovement::where('item_id', $first->id)->sole();
        $this->assertSame('0.125', $m->quantity_delta);
        $this->assertSame('1.005', $m->quantity_before);
        $this->assertSame('1.130', $m->quantity_after);
        $this->post('/stock-requests/'.$r->id.'/execute', ['confirmed' => true])->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('stock_movements', 2);
        $out = $this->draft($a->user, [['item_id' => $first->id, 'quantity' => '1.130']], 'exit');
        $this->approve($out, $a->user, $admin);
        $this->post('/stock-requests/'.$out->id.'/execute', ['confirmed' => true])->assertSessionHasNoErrors();
        $this->assertSame('0.000', $b1->fresh()->quantity);
        $this->assertSame('-1.130', StockMovement::latest('id')->first()->quantity_delta);
        $this->assertSame(1, $out->events()->where('event_type', 'executed')->count());
    }

    public function test_reassigned_requester_loses_execution_and_new_responsible_employee_can_execute(): void
    {
        $a = StorageAreaAssignment::factory()->create();
        $admin = $this->admin();
        $item = Item::factory()->create();
        $b = $this->balance($a, $item);
        $r = $this->draft($a->user, [['item_id' => $item->id, 'quantity' => '1']]);
        $this->approve($r, $a->user, $admin);
        $replacement = User::factory()->create();
        app(ChangeStorageAreaAssignment::class)->handle($a->storageArea, $replacement->id, $a->id);
        $other = StorageArea::factory()->create();
        app(ChangeStorageAreaAssignment::class)->handle($other, $a->user_id, null);
        $this->actingAs($a->user)->get('/stock-requests/'.$r->id)->assertInertia(fn (Assert $p) => $p->where('availability', null)->where('actions.execute', false));
        $this->post('/stock-requests/'.$r->id.'/execute', ['confirmed' => true])->assertForbidden();
        $this->actingAs($replacement)->get('/stock-requests?scope=execution')->assertInertia(fn (Assert $p) => $p->has('requests.data', 1));
        $this->post('/stock-requests/'.$r->id.'/execute', ['confirmed' => true])->assertSessionHasNoErrors();
        $this->assertSame($replacement->id, $r->fresh()->executed_by);
        $this->assertSame($a->storage_area_id, $r->fresh()->storage_area_id);
        $this->get('/stock-requests/'.$r->id)->assertOk();
        $this->assertSame('11.000', $b->fresh()->quantity);
    }

    public function test_insufficient_execution_stock_preserves_approval_and_rolls_back_every_line(): void
    {
        $a = StorageAreaAssignment::factory()->create();
        $admin = $this->admin();
        $first = Item::factory()->create();
        $second = Item::factory()->create();
        $b1 = $this->balance($a, $first);
        $b2 = $this->balance($a, $second);
        $r = $this->draft($a->user, [['item_id' => $first->id, 'quantity' => '7'], ['item_id' => $second->id, 'quantity' => '7']], 'exit');
        $this->approve($r, $a->user, $admin);
        $b2->forceFill(['quantity' => '1.000'])->save();
        $this->actingAs($a->user)->post('/stock-requests/'.$r->id.'/execute', ['confirmed' => true])->assertSessionHasErrors('operation');
        $this->assertSame('10.000', $b1->fresh()->quantity);
        $this->assertSame('1.000', $b2->fresh()->quantity);
        $this->assertSame(StockRequestStatus::Approved, $r->fresh()->status);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_failure_after_a_first_line_write_rolls_back_balances_movements_and_completion(): void
    {
        $a = StorageAreaAssignment::factory()->create();
        $admin = $this->admin();
        $one = Item::factory()->create();
        $two = Item::factory()->create();
        $b1 = $this->balance($a, $one);
        $b2 = $this->balance($a, $two);
        $r = $this->draft($a->user, [['item_id' => $one->id, 'quantity' => '1'], ['item_id' => $two->id, 'quantity' => '1']]);
        $this->approve($r, $a->user, $admin);
        $written = 0;
        StockMovement::creating(function () use (&$written) {
            if (++$written === 2) {
                throw new \RuntimeException('Simulated ledger storage failure.');
            }
        });
        try {
            app(StockWorkflow::class)->execute($a->user, $r);
            $this->fail('Execution should roll back.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated ledger storage failure.', $e->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.StockMovement::class);
        }
        $this->assertSame('10.000', $b1->fresh()->quantity);
        $this->assertSame('10.000', $b2->fresh()->quantity);
        $this->assertSame(StockRequestStatus::Approved, $r->fresh()->status);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(0, $r->events()->where('event_type', 'executed')->count());
    }

    public function test_connection_failure_during_execution_returns_503_and_rolls_back_every_write(): void
    {
        $a = StorageAreaAssignment::factory()->create();
        $admin = $this->admin();
        $one = Item::factory()->create();
        $two = Item::factory()->create();
        $b1 = $this->balance($a, $one);
        $b2 = $this->balance($a, $two);
        $r = $this->draft($a->user, [['item_id' => $one->id, 'quantity' => '1'], ['item_id' => $two->id, 'quantity' => '1']]);
        $this->approve($r, $a->user, $admin);
        $written = 0;
        StockMovement::creating(function () use (&$written) {
            if (++$written === 2) {
                throw DatabaseAvailabilityTest::failure();
            }
        });
        try {
            $this->actingAs($a->user)->post('/stock-requests/'.$r->id.'/execute', ['confirmed' => true], ['X-Inertia' => 'true'])
                ->assertStatus(503)->assertSee('No action has been queued.')->assertHeaderMissing('Location')->assertDontSee('secret-password');
        } finally {
            Event::forget('eloquent.creating: '.StockMovement::class);
        }
        $this->assertSame(2, $written);
        $this->assertSame('10.000', $b1->fresh()->quantity);
        $this->assertSame('10.000', $b2->fresh()->quantity);
        $this->assertSame(StockRequestStatus::Approved, $r->fresh()->status);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(0, $r->events()->where('event_type', 'executed')->count());
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_overflow_in_later_entry_line_cannot_partially_execute(): void
    {
        $a = StorageAreaAssignment::factory()->create();
        $admin = $this->admin();
        $one = Item::factory()->create();
        $two = Item::factory()->create();
        $b1 = $this->balance($a, $one);
        $b2 = $this->balance($a, $two, '999999999999999.999');
        $beforeSecond = $b2->fresh()->quantity;
        $r = $this->draft($a->user, [['item_id' => $one->id, 'quantity' => '1'], ['item_id' => $two->id, 'quantity' => '0.001']]);
        $this->approve($r, $a->user, $admin);
        $this->actingAs($a->user)->post('/stock-requests/'.$r->id.'/execute', ['confirmed' => true])->assertSessionHasErrors('operation');
        $this->assertSame('10.000', $b1->fresh()->quantity);
        $this->assertSame($beforeSecond, $b2->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_catalog_snapshots_and_request_units_survive_metadata_changes(): void
    {
        $a = StorageAreaAssignment::factory()->create();
        $admin = $this->admin();
        $item = Item::factory()->create(['name' => 'Original equipment']);
        $this->balance($a, $item);
        $r = $this->draft($a->user, [['item_id' => $item->id, 'quantity' => '1']]);
        $this->approve($r, $a->user, $admin);
        $original = $r->lines()->sole();
        $data = [...$item->only(['name', 'reference_code', 'category_id', 'unit_of_measure', 'is_active']), 'tracking_mode' => $item->tracking_mode->value];
        $this->actingAs($admin)->put('/catalog/items/'.$item->id, [...$data, 'unit_of_measure' => 'liter'])->assertSessionHasErrors('unit_of_measure');
        $this->put('/catalog/items/'.$item->id, [...$data, 'tracking_mode' => 'individual_asset'])->assertSessionHasErrors('tracking_mode');
        $this->put('/catalog/items/'.$item->id, [...$data, 'name' => 'Renamed equipment', 'reference_code' => 'RENAMED', 'is_active' => false])->assertSessionHasNoErrors();
        $this->actingAs($a->user)->post('/stock-requests/'.$r->id.'/execute', ['confirmed' => true])->assertSessionHasNoErrors();
        $this->assertSame('Original equipment', $r->lines()->sole()->item_name_snapshot);
        $this->assertSame($original->item_reference_snapshot, $r->lines()->sole()->item_reference_snapshot);
    }

    public function test_visibility_histories_dashboard_filters_and_search_are_scoped(): void
    {
        $a = StorageAreaAssignment::factory()->create();
        $other = StorageAreaAssignment::factory()->create();
        $admin = $this->admin();
        $item = Item::factory()->create(['name' => 'Searchable supply']);
        $this->balance($a, $item);
        $r = $this->draft($a->user, [['item_id' => $item->id, 'quantity' => '1']]);
        $foreign = $this->draft($other->user, [['item_id' => $item->id, 'quantity' => '1']]);
        $this->actingAs($a->user)->get('/stock-requests')->assertInertia(fn (Assert $p) => $p->has('requests.data', 1)->has('areas', 0)->where('canReview', false));
        $this->get('/stock-requests/'.$foreign->id)->assertForbidden();
        $this->get('/approval-requests')->assertForbidden();
        $this->get('/stock-requests/items?search=Searchable')->assertJsonCount(1, 'items')->assertJsonPath('items.0.name', 'Searchable supply');
        $this->approve($r, $a->user, $admin);
        $this->actingAs($admin)->get('/approval-requests')->assertInertia(fn (Assert $p) => $p->has('requests.data', 0));
        $this->get('/approval-requests?status=approved&city_id='.$a->storageArea->city_id.'&employee='.urlencode($a->user->email))->assertInertia(fn (Assert $p) => $p->has('requests.data', 1));
        $this->actingAs($a->user)->get('/dashboard')->assertInertia(fn (Assert $p) => $p->where('stock.metrics.1.value', 1));
        $this->post('/stock-requests/'.$r->id.'/execute', ['confirmed' => true])->assertSessionHasNoErrors();
        $this->get('/stock-movements')->assertInertia(fn (Assert $p) => $p->has('movements.data', 1));
        $this->actingAs($other->user)->get('/stock-movements')->assertInertia(fn (Assert $p) => $p->has('movements.data', 0));
        $this->actingAs($admin)->get('/dashboard')->assertInertia(fn (Assert $p) => $p->has('stock.recentMovements', 1)->has('stock.recentCompleted', 1));
        $this->get('/stock-requests?to='.now()->format('Y-m-d'))->assertOk();
        $this->get('/stock-requests?from=2026-10-20&to=2026-10-01')->assertSessionHasErrors('to');
    }

    public function test_role_deactivation_self_approval_and_all_direct_write_shortcuts_are_rejected(): void
    {
        $a = StorageAreaAssignment::factory()->create();
        $admin = $this->admin();
        $item = Item::factory()->create();
        $this->balance($a, $item);
        $r = $this->draft($a->user, [['item_id' => $item->id, 'quantity' => '1']]);
        app(StockWorkflow::class)->submit($a->user, $r);
        app(ChangeStorageAreaAssignment::class)->handle($a->storageArea, null, $a->id);
        app(ManageEmployee::class)->access($admin, $a->user, Role::Admin, null);
        $promoted = $a->user->fresh();
        $this->actingAs($promoted)->withSession(['auth_version' => $promoted->auth_version])->post('/stock-requests/'.$r->id.'/approve')->assertForbidden();
        $this->put('/stock-requests/'.$r->id.'/status', ['status' => 'completed'])->assertNotFound();
        $this->delete('/stock-requests/'.$r->id)->assertMethodNotAllowed();
        $this->put('/stock-movements/1', ['quantity_delta' => '500'])->assertNotFound();
        $this->put('/inventory/'.$item->id, ['quantity' => '500'])->assertMethodNotAllowed();
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_inactive_users_and_guests_cannot_transition_or_browse_protected_routes(): void
    {
        $a = StorageAreaAssignment::factory()->create();
        $item = Item::factory()->create();
        $r = $this->draft($a->user, [['item_id' => $item->id, 'quantity' => '1']]);
        foreach (['/stock-requests', '/stock-requests/create', '/stock-requests/'.$r->id, '/stock-movements', '/approval-requests'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        foreach (['submit', 'approve', 'reject', 'execute', 'cancel'] as $action) {
            $this->post('/stock-requests/'.$r->id.'/'.$action)->assertRedirect('/login');
        }
        $inactive = User::factory()->create(['role' => Role::Admin, 'is_active' => false]);
        foreach (['submit', 'approve', 'reject', 'execute'] as $action) {
            $this->actingAs($inactive)->post('/stock-requests/'.$r->id.'/'.$action, ['confirmed' => true, 'rejection_reason' => 'No'])->assertRedirect('/login');
        }
    }

    public function test_database_constraints_protect_positive_lines_ledger_identity_and_immutable_routes(): void
    {
        $a = StorageAreaAssignment::factory()->create();
        $admin = $this->admin();
        $item = Item::factory()->create();
        $this->balance($a, $item);
        $r = $this->draft($a->user, [['item_id' => $item->id, 'quantity' => '1']]);
        $this->approve($r, $a->user, $admin);
        app(StockWorkflow::class)->execute($a->user, $r);
        $line = $r->lines()->sole();
        $movement = StockMovement::sole();
        foreach ([
            fn () => DB::table('stock_request_items')->where('id', $line->id)->update(['quantity' => 0]),
            fn () => DB::table('stock_movements')->insert([...$movement->getAttributes(), 'id' => $movement->id + 1]),
            fn () => DB::table('stock_requests')->where('id', $r->id)->delete(),
            fn () => DB::table('items')->where('id', $item->id)->delete(),
        ] as $operation) {
            try {
                DB::transaction($operation);
                $this->fail('Invalid stock integrity operation accepted.');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(1, StockMovement::count());
    }
}
