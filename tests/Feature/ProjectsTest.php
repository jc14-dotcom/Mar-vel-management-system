<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_open_project_directory(): void
    {
        $this->withoutVite();

        $this->actingAs($this->employee())
            ->get('/projects')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Projects/Index'));
    }

    public function test_project_directory_requires_voucher_view_or_project_management(): void
    {
        $employee = $this->employee();
        $viewVouchers = Permission::query()->where('name', 'vouchers.view')->firstOrFail();
        $employee->roles()->firstOrFail()->permissions()->detach($viewVouchers);

        $this->actingAs($employee)
            ->get('/projects')
            ->assertForbidden();
    }

    public function test_project_managers_can_open_directory_without_voucher_view(): void
    {
        $this->withoutVite();
        $admin = $this->superAdmin();
        $viewVouchers = Permission::query()->where('name', 'vouchers.view')->firstOrFail();
        $admin->roles()->firstOrFail()->permissions()->detach($viewVouchers);
        $this->assertTrue($admin->fresh()->hasPermission('projects.manage'));
        $this->assertFalse($admin->fresh()->hasPermission('vouchers.view'));

        $this->actingAs($admin)
            ->get('/projects')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Projects/Index'));
    }

    public function test_project_directory_returns_paginated_searchable_projects_and_active_options(): void
    {
        $this->withoutVite();
        $employee = $this->employee();

        foreach (range(1, 11) as $number) {
            Project::create([
                'name' => "Project {$number}",
                'normalized_name' => "project {$number}",
            ]);
        }

        $this->actingAs($employee)
            ->get('/projects?search=Project%2011')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Index')
                ->where('filters.search', 'Project 11')
                ->where('projects.current_page', 1)
                ->where('projects.per_page', 10)
                ->where('projects.total', 1)
                ->has('projects.data', 1)
                ->where('projects.data.0.name', 'Project 11')
                ->where('activeProjectCount', 11)
                ->has('projectOptions', 11));
    }

    public function test_project_managers_can_add_projects_without_voucher_permissions(): void
    {
        $admin = $this->superAdmin();
        $viewVouchers = Permission::query()->where('name', 'vouchers.view')->firstOrFail();
        $createVouchers = Permission::query()->where('name', 'vouchers.create')->firstOrFail();
        $admin->roles()->firstOrFail()->permissions()->detach([$viewVouchers->id, $createVouchers->id]);

        $this->actingAs($admin)
            ->post('/projects', ['name' => 'Main Building'])
            ->assertRedirect('/projects');

        $this->assertDatabaseHas('projects', [
            'name' => 'Main Building',
            'normalized_name' => 'main building',
        ]);
    }

    public function test_employee_can_create_a_whitespace_normalized_project(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)
            ->post('/projects', ['name' => '  West   Wing  '])
            ->assertRedirect('/projects');

        $project = Project::query()->sole();
        $this->assertSame('West Wing', $project->name);
        $this->assertSame('west wing', $project->normalized_name);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $employee->id,
            'action' => 'project.created',
            'auditable_id' => $project->id,
        ]);
    }

    public function test_employee_project_creation_reuses_case_and_whitespace_match(): void
    {
        $employee = $this->employee();
        Project::create(['name' => 'West Wing', 'normalized_name' => 'west wing']);

        $this->actingAs($employee)
            ->post('/projects', ['name' => ' WEST   wing '])
            ->assertRedirect('/projects');

        $this->assertSame(1, Project::query()->count());
    }

    public function test_new_project_is_not_created_from_whitespace_only_input(): void
    {
        $this->actingAs($this->employee())
            ->post('/projects', ['name' => '   '])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Project::query()->count());
    }

    public function test_employees_without_project_creation_permission_cannot_add_projects(): void
    {
        $employee = $this->employee();
        $createVouchers = Permission::query()->where('name', 'vouchers.create')->firstOrFail();
        $employee->roles()->firstOrFail()->permissions()->detach($createVouchers);

        $this->actingAs($employee)
            ->post('/projects', ['name' => 'West Wing'])
            ->assertForbidden();

        $this->assertSame(0, Project::query()->count());
    }

    public function test_employees_cannot_rename_projects(): void
    {
        $employee = $this->employee();
        $project = Project::create(['name' => 'West Wing', 'normalized_name' => 'west wing']);
        $target = Project::create(['name' => 'Main Building', 'normalized_name' => 'main building']);

        $this->actingAs($employee)
            ->patch("/admin/projects/{$project->id}", ['name' => 'East Wing'])
            ->assertForbidden();
        $this->actingAs($employee)
            ->post("/admin/projects/{$project->id}/merge", ['target_project_id' => $target->id])
            ->assertForbidden();

        $this->assertSame('West Wing', $project->fresh()->name);
        $this->assertNull($project->fresh()->merged_into_id);
    }

    public function test_super_admin_can_rename_a_project_and_audit_the_change(): void
    {
        $admin = $this->superAdmin();
        $project = Project::create(['name' => 'West Wing', 'normalized_name' => 'west wing']);

        $this->actingAs($admin)
            ->patch("/admin/projects/{$project->id}", ['name' => '  East   Wing '])
            ->assertRedirect('/projects');

        $this->assertSame('East Wing', $project->fresh()->name);
        $this->assertSame('east wing', $project->fresh()->normalized_name);
        $this->assertSame([
            'before' => ['name' => 'West Wing'],
            'after' => ['name' => 'East Wing'],
        ], AuditEvent::query()->where('action', 'project.renamed')->sole()->metadata);
    }

    public function test_project_rename_rejects_an_existing_normalized_name(): void
    {
        $admin = $this->superAdmin();
        $project = Project::create(['name' => 'West Wing', 'normalized_name' => 'west wing']);
        Project::create(['name' => 'East Wing', 'normalized_name' => 'east wing']);

        $this->actingAs($admin)
            ->patch("/admin/projects/{$project->id}", ['name' => ' EAST   WING '])
            ->assertSessionHasErrors('name');

        $this->assertSame('West Wing', $project->fresh()->name);
    }

    public function test_super_admin_can_merge_projects_without_deleting_the_source(): void
    {
        $admin = $this->superAdmin();
        $source = Project::create(['name' => 'West Wing', 'normalized_name' => 'west wing']);
        $target = Project::create(['name' => 'Main Building', 'normalized_name' => 'main building']);

        $this->actingAs($admin)
            ->post("/admin/projects/{$source->id}/merge", ['target_project_id' => $target->id])
            ->assertRedirect('/projects');

        $this->assertModelExists($source->fresh());
        $this->assertSame($target->id, $source->fresh()->merged_into_id);
        $this->assertSame('West Wing', $source->fresh()->name);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $admin->id,
            'action' => 'project.merged',
            'auditable_id' => $source->id,
        ]);
        $this->assertSame([
            'before' => ['merged_into_id' => null],
            'after' => [
                'merged_into_id' => $target->id,
                'target_name' => 'Main Building',
            ],
        ], AuditEvent::query()->where('action', 'project.merged')->sole()->metadata);
    }

    public function test_adding_a_merged_project_name_resolves_to_its_active_target(): void
    {
        $employee = $this->employee();
        $source = Project::create(['name' => 'West Wing', 'normalized_name' => 'west wing']);
        $target = Project::create(['name' => 'Main Building', 'normalized_name' => 'main building']);
        $source->update(['merged_into_id' => $target->id]);

        $this->actingAs($employee)
            ->post('/projects', ['name' => 'West Wing'])
            ->assertRedirect('/projects')
            ->assertSessionHas('success', 'Main Building is available in the project list.');

        $this->assertSame(2, Project::query()->count());
        $this->assertSame($target->id, $source->fresh()->merged_into_id);
    }

    public function test_super_admin_cannot_merge_a_project_into_itself(): void
    {
        $admin = $this->superAdmin();
        $project = Project::create(['name' => 'West Wing', 'normalized_name' => 'west wing']);

        $this->actingAs($admin)
            ->post("/admin/projects/{$project->id}/merge", ['target_project_id' => $project->id])
            ->assertSessionHasErrors('target_project_id');

        $this->assertNull($project->fresh()->merged_into_id);
    }

    private function employee(): User
    {
        $this->seed();
        $employee = User::factory()->create();
        $employee->roles()->attach(Role::where('name', 'employee')->firstOrFail());

        return $employee;
    }

    private function superAdmin(): User
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'super_admin')->firstOrFail());

        return $admin;
    }
}
