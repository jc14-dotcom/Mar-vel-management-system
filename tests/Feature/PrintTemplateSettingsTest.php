<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\AuditEvent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintTemplateSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_print_template_settings_with_defaults(): void
    {
        $this->withoutVite();

        $this->actingAs($this->superAdmin())
            ->get('/admin/settings')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Settings')
                ->where('settings.form_number', 'MCS-OP-ACC-01F3'));
    }

    public function test_super_admin_can_update_print_template_settings_and_audit_the_change(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->patch('/admin/settings', [
                'company_name' => 'Mar-vel Office',
                'form_number' => 'MCS-OP-ACC-01F3',
                'effective_date' => '2026-11-26',
                'template_revision' => '1.1',
                'procedural_notices' => 'Attach approved supporting documents.',
                'unapproved_setting' => 'must not persist',
            ])
            ->assertRedirect('/admin/settings');

        $this->assertDatabaseCount('app_settings', 1);
        $after = [
            'company_name' => 'Mar-vel Office',
            'form_number' => 'MCS-OP-ACC-01F3',
            'effective_date' => '2026-11-26',
            'template_revision' => '1.1',
            'procedural_notices' => 'Attach approved supporting documents.',
        ];
        $this->assertSame($after, AppSetting::query()->sole()->value);
        $this->assertSame([
            'before' => [
                'company_name' => '',
                'form_number' => 'MCS-OP-ACC-01F3',
                'effective_date' => '',
                'template_revision' => '1',
                'procedural_notices' => '',
            ],
            'after' => $after,
        ], AuditEvent::query()->where('action', 'settings.print_template_updated')->sole()->metadata);
    }

    public function test_employees_cannot_open_or_update_print_template_settings(): void
    {
        $this->seed();
        $employee = User::factory()->create();
        $employee->roles()->attach(Role::where('name', 'employee')->firstOrFail());

        $this->actingAs($employee)
            ->get('/admin/settings')
            ->assertForbidden();

        $this->actingAs($employee)
            ->patch('/admin/settings', ['form_number' => 'UNAUTHORIZED'])
            ->assertForbidden();

        $this->assertDatabaseCount('app_settings', 0);
    }

    public function test_print_template_settings_reject_an_invalid_effective_date(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->patch('/admin/settings', [
                'company_name' => 'Mar-vel Office',
                'form_number' => 'MCS-OP-ACC-01F3',
                'effective_date' => 'not-a-date',
                'template_revision' => '1.1',
                'procedural_notices' => '',
            ])
            ->assertSessionHasErrors('effective_date');

        $this->assertDatabaseCount('app_settings', 0);
    }

    private function superAdmin(): User
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'super_admin')->firstOrFail());

        return $admin;
    }
}
