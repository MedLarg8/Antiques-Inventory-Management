<?php

namespace Tests\Feature;

use App\Actions\ManageEmployee;
use App\Enums\Role;
use App\Models\EmployeeAudit;
use App\Models\StorageArea;
use App\Models\StorageAreaAssignment;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin]);
    }

    public function test_directory_filters_sorting_pagination_and_admin_only_profile_fields(): void
    {
        $admin = $this->admin();
        $assignment = StorageAreaAssignment::factory()->create();
        $assignment->user->forceFill(['administrative_notes' => 'Private personnel note', 'phone' => '12345'])->save();
        User::factory()->count(17)->create(['is_active' => false]);
        $this->actingAs($admin)->get('/users?sort=email&direction=desc')->assertInertia(fn (Assert $p) => $p->component('users/index')->has('users.data', 15)->where('users.total', 19)->missing('users.data.0.password')->missing('users.data.0.administrative_notes'));
        $this->get('/users?role=stock_employee&status=active&assignment=assigned&city_id='.$assignment->storageArea->city_id.'&storage_area_id='.$assignment->storage_area_id)
            ->assertInertia(fn (Assert $p) => $p->has('users.data', 1)->where('users.data.0.id', $assignment->user_id));
        $this->get('/users/'.$assignment->user_id)->assertInertia(fn (Assert $p) => $p->where('employee.administrative_notes', 'Private personnel note')->where('current.id', $assignment->id));
        $this->get('/users?sort=password')->assertSessionHasErrors('sort');
        $this->actingAs($assignment->user)->get('/dashboard')->assertInertia(fn (Assert $p) => $p->where('employees', null)->missing('auth.user.administrative_notes'));
    }

    public function test_administrator_can_create_roles_and_edit_profiles_without_setting_security_fields(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->actingAs($admin)->post('/users', ['name' => 'Second Admin', 'email' => 'SECOND@example.test', 'role' => 'admin', 'is_active' => true, 'phone' => '123', 'administrative_notes' => 'Notes', 'password' => 'guessed', 'email_verified_at' => now()])->assertSessionHasNoErrors();
        $target = User::where('email', 'second@example.test')->sole();
        $this->assertSame(Role::Admin, $target->role);
        $this->assertNull($target->email_verified_at);
        $this->assertFalse(Hash::check('guessed', $target->password));
        $this->post('/users', ['name' => 'Wrong', 'email' => 'bad@example.test', 'role' => 'invalid'])->assertSessionHasErrors('role');
        $this->put('/users/'.$target->id, ['name' => 'Updated', 'email' => $target->email, 'phone' => '456', 'administrative_notes' => 'Updated notes', 'role' => 'stock_employee', 'is_active' => false])->assertSessionHasNoErrors();
        $target->refresh();
        $this->assertSame('456', $target->phone);
        $this->assertSame(Role::Admin, $target->role);
        $this->assertTrue($target->is_active);
        $this->put('/users/'.$target->id, ['name' => 'Updated', 'email' => $admin->email])->assertSessionHasErrors('email');
        $audit = EmployeeAudit::where('action', 'employee_created')->sole();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame($target->id, $audit->employee_id);
        $this->assertStringNotContainsString('guessed', EmployeeAudit::all()->toJson());
        $this->assertStringNotContainsString('Updated notes', EmployeeAudit::all()->toJson());
    }

    public function test_profile_email_change_clears_verification_tokens_and_sessions(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['remember_token' => 'oldremember']);
        Password::createToken($target);
        $this->actingAs($admin)->put('/users/'.$target->id, ['name' => 'Renamed', 'email' => 'changed@example.test'])->assertSessionHasNoErrors();
        $target->refresh();
        $this->assertNull($target->email_verified_at);
        $this->assertNull($target->remember_token);
        $this->assertSame(1, $target->auth_version);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'changed@example.test']);
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_last_admin_self_deactivation_and_self_deletion_are_protected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->put('/users/'.$admin->id.'/role', ['role' => 'stock_employee'])->assertSessionHasErrors('account');
        $this->put('/users/'.$admin->id.'/status', ['is_active' => false])->assertSessionHasErrors('account');
        $this->delete('/settings/profile', ['password' => 'password'])->assertSessionHasErrors('account');
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertSame(Role::Admin, $admin->fresh()->role);
        $this->admin();
        $this->put('/users/'.$admin->id.'/status', ['is_active' => false])->assertSessionHasErrors('account');
    }

    public function test_role_change_requires_ending_assignment_and_revokes_old_privileges(): void
    {
        $admin = $this->admin();
        $assignment = StorageAreaAssignment::factory()->create();
        $target = $assignment->user;
        $this->actingAs($admin)->put('/users/'.$target->id.'/role', ['role' => 'admin'])->assertSessionHasErrors('account');
        $this->put('/storage-areas/'.$assignment->storage_area_id.'/assignment', ['user_id' => null, 'expected_assignment_id' => $assignment->id])->assertSessionHasNoErrors();
        $this->put('/users/'.$target->id.'/role', ['role' => 'admin'])->assertSessionHasNoErrors();
        $this->assertNotNull($assignment->fresh()->ended_at);
        $this->assertSame(Role::Admin, $target->fresh()->role);
        // A stale pre-change session cannot acquire privileges after promotion.
        $this->actingAs($target)->withSession(['auth_version' => 0])->get('/users')->assertRedirect('/login');
        $this->post('/login', ['email' => $target->email, 'password' => 'password'])->assertSessionHasNoErrors();
        $this->get('/users')->assertOk();
        app(ManageEmployee::class)->access($admin, $target, Role::StockEmployee, null);
        $this->get('/users')->assertRedirect('/login');
        $this->post('/login', ['email' => $target->email, 'password' => 'password'])->assertSessionHasNoErrors();
        $this->get('/users')->assertForbidden();
    }

    public function test_deactivation_requires_ending_assignment_and_reactivation_never_restores_it(): void
    {
        $admin = $this->admin();
        $assignment = StorageAreaAssignment::factory()->create();
        $target = $assignment->user;
        $this->actingAs($admin)->put('/users/'.$target->id.'/status', ['is_active' => false])->assertSessionHasErrors('account');
        $this->put('/storage-areas/'.$assignment->storage_area_id.'/assignment', ['user_id' => null, 'expected_assignment_id' => $assignment->id])->assertSessionHasNoErrors();
        $this->put('/users/'.$target->id.'/status', ['is_active' => false])->assertSessionHasNoErrors();
        $area = StorageArea::factory()->create();
        $this->put('/storage-areas/'.$area->id.'/assignment', ['user_id' => $target->id, 'expected_assignment_id' => null])->assertSessionHasErrors('user_id');
        $this->put('/users/'.$target->id.'/status', ['is_active' => true])->assertSessionHasNoErrors();
        $this->assertNull($target->fresh()->currentStorageArea);
        $this->assertNotNull($assignment->fresh()->ended_at);
        $this->assertDatabaseHas('employee_audits', ['action' => 'depot_responsibility_changed', 'employee_id' => $target->id, 'actor_id' => $admin->id]);
        $this->assertDatabaseHas('employee_audits', ['action' => 'employee_deactivated', 'employee_id' => $target->id]);
        $this->assertDatabaseHas('employee_audits', ['action' => 'employee_activated', 'employee_id' => $target->id]);
    }

    public function test_deactivated_user_loses_old_session_and_cannot_sign_in_until_reactivated(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['remember_token' => 'remember-before']);
        $this->post('/login', ['email' => $target->email, 'password' => 'password', 'remember' => true])->assertSessionHasNoErrors();
        $this->get('/dashboard')->assertOk();
        app(ManageEmployee::class)->access($admin, $target, null, false);
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
        $this->post('/login', ['email' => $target->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        app(ManageEmployee::class)->access($admin, $target, null, true);
        $this->actingAs($target)->withSession(['auth_version' => 0])->get('/dashboard')->assertRedirect('/login');
        $this->post('/login', ['email' => $target->email, 'password' => 'password'])->assertSessionHasNoErrors();
        $this->get('/dashboard')->assertOk();
        $this->assertNotNull($target->fresh()->last_login_at);
        $this->assertNotSame('remember-before', $target->fresh()->remember_token);
    }

    public function test_database_session_revocation_targets_only_affected_user_and_other_driver_fallback_denies_access(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        foreach ([['target-session', $target], ['other-session', $admin]] as [$id,$user]) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        }
        config(['session.driver' => 'database']);
        app(ManageEmployee::class)->access($admin, $target, null, false);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
        config(['session.driver' => 'array']);
        $this->actingAs($target)->get('/settings/profile')->assertRedirect('/login');
    }

    public function test_pending_two_factor_challenge_is_revoked_when_access_changes(): void
    {
        $admin = $this->admin();
        $target = User::factory()->withTwoFactor()->create();
        $this->post('/login', ['email' => $target->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        app(ManageEmployee::class)->access($admin, $target, null, false);
        app(ManageEmployee::class)->access($admin, $target, null, true);
        $this->get('/two-factor-challenge')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_management_routes_deny_employees_guests_and_inactive_administrators(): void
    {
        $target = User::factory()->create();
        $employee = User::factory()->create();
        foreach ([null, $employee, User::factory()->create(['role' => Role::Admin, 'is_active' => false])] as $actor) {
            Auth::forgetGuards();
            $this->app['session']->flush();
            if ($actor) {
                $this->actingAs($actor);
            }
            foreach (['/users', '/users/create', '/users/'.$target->id, '/users/'.$target->id.'/edit'] as $url) {
                $response = $this->get($url);
                if ($actor && $actor->is_active) {
                    $response->assertForbidden();
                } else {
                    $response->assertRedirect('/login');
                }
            }
            foreach (['/users/'.$target->id.'/role' => ['role' => 'admin'], '/users/'.$target->id.'/status' => ['is_active' => false], '/users/'.$target->id => ['name' => 'Hacked', 'email' => 'hacked@example.test']] as $url => $data) {
                $response = $this->put($url, $data);
                if ($actor && $actor->is_active) {
                    $response->assertForbidden();
                } else {
                    $response->assertRedirect('/login');
                }
            }
            foreach (['/users/'.$target->id.'/invite', '/users/'.$target->id.'/setup-link', '/users'] as $url) {
                $response = $this->post($url);
                if ($actor && $actor->is_active) {
                    $response->assertForbidden();
                } else {
                    $response->assertRedirect('/login');
                }
            }
        }
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    public function test_private_setup_links_are_hashed_expiring_single_use_and_do_not_verify_new_email_silently(): void
    {
        $admin = $this->admin();
        $target = User::factory()->unverified()->create();
        $response = $this->actingAs($admin)->post('/users/'.$target->id.'/setup-link')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $page = $response->viewData('page');
        $url = $page['props']['setupUrl'];
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $token = basename(parse_url($url, PHP_URL_PATH));
        $this->assertNotSame($token, DB::table('password_reset_tokens')->where('email', $target->email)->value('token'));
        $this->assertNull($target->fresh()->email_verified_at);
        $this->assertStringNotContainsString($token, EmployeeAudit::all()->toJson());
        $this->assertStringNotContainsString($token, json_encode(session()->all()));
        $this->get('/users/'.$target->id.'/setup-link')->assertMethodNotAllowed();
        $this->post('/logout');
        $payload = ['token' => $token, 'email' => $query['email'], 'password' => 'Secure-new-password-127', 'password_confirmation' => 'Secure-new-password-127'];
        $this->post('/reset-password', $payload)->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check($payload['password'], $target->fresh()->password));
        $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $target->email, 'password' => $payload['password']])->assertSessionHasNoErrors();
        $this->get($page['props']['verificationUrl'])->assertRedirect('/dashboard?verified=1');
        $this->assertNotNull($target->fresh()->email_verified_at);
    }

    public function test_local_link_expiry_and_log_mail_does_not_emit_authentication_secrets(): void
    {
        $target = User::factory()->unverified()->create();
        $token = Password::createToken($target);
        $this->travel(61)->minutes();
        $this->post('/reset-password', ['token' => $token, 'email' => $target->email, 'password' => 'Secure-new-password-127', 'password_confirmation' => 'Secure-new-password-127'])->assertSessionHasErrors('email');
        Notification::fake();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->app->instance('env', 'local');
        config(['mail.default' => 'log']);
        $target->sendPasswordResetNotification('secret-reset-token');
        $target->sendEmailVerificationNotification();
        Notification::assertNothingSent();
        $this->actingAs($this->admin())->post('/users/'.$target->id.'/invite')->assertSessionHasErrors('onboarding');
    }

    public function test_employee_dashboard_counts_and_read_only_audit_history(): void
    {
        $admin = $this->admin();
        $inactive = User::factory()->create(['is_active' => false]);
        $active = User::factory()->create();
        $this->actingAs($admin)->get('/dashboard')->assertInertia(fn (Assert $p) => $p->where('employees.Total accounts', 3)->where('employees.Inactive accounts', 1)->where('employees.Active depot employees without assignments', 1));
        app(ManageEmployee::class)->access($admin, $active, Role::Admin, null);
        $audit = EmployeeAudit::sole();
        $this->put('/employee-audits/'.$audit->id, ['action' => 'changed'])->assertNotFound();
        $this->delete('/employee-audits/'.$audit->id)->assertNotFound();
        $this->assertSame('role_changed', $audit->fresh()->action);
        $this->actingAs($inactive)->get('/users/'.$active->id)->assertRedirect('/login');
    }
}
