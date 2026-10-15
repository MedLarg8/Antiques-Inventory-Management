<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\DevelopmentUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_workspace_route_requires_authentication(): void
    {
        foreach (['dashboard', 'inventory', 'storage-areas', 'stock-movements', 'approval-requests', 'documents', 'reports', 'administration'] as $page) {
            $this->get('/'.$page)->assertRedirect('/login');
        }
    }

    public function test_both_roles_receive_their_role_and_dashboard(): void
    {
        foreach (Role::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get('/dashboard')->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                    ->where('auth.user.role', $role->value)
                    ->missing('auth.user.password')
                    ->missing('auth.user.two_factor_secret'));
            $this->assertSame($role, $user->fresh()->role);
        }
    }

    public function test_administration_is_enforced_on_the_server(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::StockEmployee]))
            ->get('/administration')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => Role::Admin]))
            ->get('/administration')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('module-placeholder')->where('title', 'Administration'));
    }

    public function test_shared_modules_are_protected_placeholders_for_both_roles(): void
    {
        foreach (Role::cases() as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['inventory', 'storage-areas', 'stock-movements', 'approval-requests', 'documents', 'reports'] as $page) {
                $this->get('/'.$page)->assertOk()
                    ->assertInertia(fn (Assert $page) => $page->component('module-placeholder'));
            }
        }
    }

    public function test_profile_update_cannot_escalate_role(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch('/settings/profile', [
            'name' => $user->name, 'email' => $user->email, 'role' => 'admin',
        ])->assertSessionHasNoErrors();
        $this->assertSame(Role::StockEmployee, $user->fresh()->role);
    }

    public function test_role_is_not_mass_assignable(): void
    {
        $user = User::factory()->create();
        $user->fill(['role' => Role::Admin]);
        $this->assertSame(Role::StockEmployee, $user->role);
    }

    public function test_development_seeder_refuses_production(): void
    {
        $this->app->instance('env', 'production');
        try {
            (new DevelopmentUserSeeder)->run();
            $this->fail('Development seeder must refuse production.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('local environment', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
    }
}
