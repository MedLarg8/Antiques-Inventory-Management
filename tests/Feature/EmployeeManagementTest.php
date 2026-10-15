<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\StorageAreaAssignment;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_provisions_stock_employee_with_secret_random_password_and_setup_notification(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->create(['role' => Role::Admin]))
            ->post('/users', ['name' => 'New Employee', 'email' => 'EMPLOYEE@example.test', 'role' => 'admin', 'password' => 'injected-password', 'email_verified_at' => now()])
            ->assertSessionHasNoErrors()->assertRedirect('/users');
        $employee = User::where('email', 'employee@example.test')->sole();
        $this->assertSame(Role::StockEmployee, $employee->role);
        $this->assertNull($employee->email_verified_at);
        $this->assertFalse(Hash::check('injected-password', $employee->password));
        Notification::assertSentTo($employee, ResetPassword::class);
        $this->get('/users?search=New')->assertInertia(fn (Assert $page) => $page->component('users/index')->has('users.data', 1)
            ->where('users.data.0.role', 'stock_employee')->missing('users.data.0.password')->missing('users.data.0.two_factor_secret'));
        $this->post('/users', ['name' => 'Duplicate', 'email' => 'employee@example.test'])->assertSessionHasErrors('email');
        $this->post('/users', ['name' => '', 'email' => 'invalid'])->assertSessionHasErrors(['name', 'email']);
    }

    public function test_account_list_shows_assignment_and_admin_can_resend_setup_link(): void
    {
        Notification::fake();
        $assignment = StorageAreaAssignment::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::Admin]))
            ->get('/users?search='.urlencode($assignment->user->email))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.current_storage_area.id', $assignment->storage_area_id));
        $this->post('/users/'.$assignment->user_id.'/invite')->assertRedirect('/users');
        Notification::assertSentTo($assignment->user, ResetPassword::class);
    }

    public function test_provisioned_employee_can_set_password_using_the_existing_reset_flow(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->create(['role' => Role::Admin]))->post('/users', ['name' => 'Invitee', 'email' => 'invitee@example.test']);
        $user = User::where('email', 'invitee@example.test')->sole();
        $notification = Notification::sent($user, ResetPassword::class)->first();
        $this->post('/logout');
        $this->post('/reset-password', ['token' => $notification->token, 'email' => $user->email, 'password' => 'A-new-test-password-48', 'password_confirmation' => 'A-new-test-password-48'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('A-new-test-password-48', $user->fresh()->password));
        $this->post('/login', ['email' => $user->email, 'password' => 'A-new-test-password-48'])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect('/email/verify');
    }
}
