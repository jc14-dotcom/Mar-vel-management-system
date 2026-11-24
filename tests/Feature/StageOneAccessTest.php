<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageOneAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_screen(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_employees_cannot_open_account_administration(): void
    {
        $this->seed();
        $employee = User::factory()->create();
        $employee->roles()->attach(Role::where('name', 'employee')->firstOrFail());

        $this->actingAs($employee)->get('/admin/accounts')->assertForbidden();
    }

    public function test_super_admins_can_open_account_administration(): void
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'super_admin')->firstOrFail());

        $this->actingAs($admin)->get('/admin/accounts')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Admin/Accounts'));
    }

    public function test_disabled_accounts_cannot_authenticate(): void
    {
        $this->seed();
        $user = User::factory()->create(['is_active' => false]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
    }

    public function test_super_admin_can_update_role_permissions_and_the_change_is_audited(): void
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'super_admin')->firstOrFail());
        $employee = Role::where('name', 'employee')->firstOrFail();
        $view = Permission::where('name', 'vouchers.view')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.permissions.roles.update', $employee), [
                'permissions' => [$view->id],
            ])
            ->assertRedirect(route('admin.permissions.index'));

        $this->assertEquals([$view->id], $employee->fresh()->permissions()->pluck('permissions.id')->all());
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $admin->id,
            'action' => 'role.permissions_updated',
            'auditable_id' => $employee->id,
        ]);
    }

    public function test_employees_cannot_update_role_permissions(): void
    {
        $this->seed();
        $employeeUser = User::factory()->create();
        $employeeUser->roles()->attach(Role::where('name', 'employee')->firstOrFail());
        $employeeRole = Role::where('name', 'employee')->firstOrFail();

        $this->actingAs($employeeUser)
            ->patch(route('admin.permissions.roles.update', $employeeRole), ['permissions' => []])
            ->assertForbidden();
    }

    public function test_new_accounts_are_required_to_change_their_temporary_password(): void
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'super_admin')->firstOrFail());

        $this->actingAs($admin)
            ->post(route('admin.accounts.store'), [
                'name' => 'New Employee',
                'email' => 'new.employee@example.test',
                'password' => 'temporary-password',
                'password_confirmation' => 'temporary-password',
                'role' => 'employee',
            ])
            ->assertRedirect(route('admin.accounts.index'));

        $newUser = User::where('email', 'new.employee@example.test')->firstOrFail();
        $this->assertTrue($newUser->must_change_password);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $admin->id,
            'action' => 'account.created',
            'auditable_id' => $newUser->id,
        ]);

        $this->post('/login', [
            'email' => $newUser->email,
            'password' => 'temporary-password',
        ])->assertRedirect(route('profile.edit', absolute: false));
    }

    public function test_changing_a_required_password_clears_the_requirement(): void
    {
        $this->seed();
        $user = User::factory()->create([
            'password' => 'temporary-password',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->put(route('password.update'), [
                'current_password' => 'temporary-password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertRedirect();

        $this->assertFalse($user->fresh()->must_change_password);
    }
}
