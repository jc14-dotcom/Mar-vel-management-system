<?php

namespace Tests\Feature;

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
}
