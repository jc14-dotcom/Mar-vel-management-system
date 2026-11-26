<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VoucherPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_voucher_list_is_searchable_and_exposes_only_matching_vouchers(): void
    {
        $employee = $this->employee();
        $project = Project::factory()->create(['name' => 'West Wing']);
        Voucher::factory()->create([
            'voucher_number' => 'DV-2026-0001',
            'payee' => 'Northwind Supplies',
            'project_id' => $project->id,
            'project_name_snapshot' => 'West Wing',
        ]);
        Voucher::factory()->create([
            'voucher_number' => 'DV-2026-0002',
            'payee' => 'Different Supplier',
            'project_id' => $project->id,
            'project_name_snapshot' => 'West Wing',
        ]);

        $this->actingAs($employee)
            ->get('/vouchers?search=Northwind')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('Vouchers/Index')
                ->where('filters.search', 'Northwind')
                ->where('vouchers.total', 1)
                ->where('vouchers.data.0.voucher_number', 'DV-2026-0001')
                ->where('vouchers.data.0.project_name_snapshot', 'West Wing'));
    }

    public function test_voucher_list_can_filter_by_status(): void
    {
        $employee = $this->employee();
        Voucher::factory()->create([
            'voucher_number' => 'DV-2026-0001',
            'status' => 'draft',
        ]);
        Voucher::factory()->create([
            'voucher_number' => 'DV-2026-0002',
            'status' => 'cancelled',
        ]);

        $this->actingAs($employee)
            ->get('/vouchers?status=cancelled')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('Vouchers/Index')
                ->where('filters.status', 'cancelled')
                ->where('vouchers.total', 1)
                ->where('vouchers.data.0.status', 'cancelled'));
    }

    public function test_voucher_pages_enforce_view_create_and_edit_permissions(): void
    {
        $employee = $this->employee();
        $voucher = Voucher::factory()->create();
        $role = $employee->roles()->firstOrFail();
        $permissions = Permission::query()
            ->whereIn('name', ['vouchers.view', 'vouchers.create', 'vouchers.edit'])
            ->get()
            ->keyBy('name');

        $role->permissions()->detach($permissions['vouchers.create']);
        $role->permissions()->detach($permissions['vouchers.edit']);

        $this->actingAs($employee)
            ->get('/vouchers')
            ->assertSuccessful();
        $this->actingAs($employee)
            ->get('/vouchers/create')
            ->assertForbidden();
        $this->actingAs($employee)
            ->get("/vouchers/{$voucher->id}/edit")
            ->assertForbidden();
    }

    public function test_voucher_list_and_history_require_view_permission(): void
    {
        $employee = $this->employee();
        $voucher = Voucher::factory()->create();
        $viewPermission = Permission::query()->where('name', 'vouchers.view')->firstOrFail();
        $employee->roles()->firstOrFail()->permissions()->detach($viewPermission);

        $this->actingAs($employee)
            ->get('/vouchers')
            ->assertForbidden();
        $this->actingAs($employee)
            ->get("/vouchers/{$voucher->id}/history")
            ->assertForbidden();
    }

    public function test_create_page_prefills_prepared_by_and_lists_active_projects(): void
    {
        $employee = $this->employee();
        $project = Project::factory()->create(['name' => 'West Wing']);

        $this->actingAs($employee)
            ->get('/vouchers/create')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('Vouchers/Form')
                ->where('voucher', null)
                ->where('preparedBy', $employee->name)
                ->has('projects', 1)
                ->where('projects.0.id', $project->id));
    }

    public function test_voucher_editor_receives_version_items_and_active_projects(): void
    {
        $employee = $this->employee();
        $project = Project::factory()->create(['name' => 'West Wing']);
        $voucher = Voucher::factory()->create([
            'project_id' => $project->id,
            'project_name_snapshot' => 'West Wing',
        ]);
        DB::table('vouchers')->where('id', $voucher->id)->update(['record_version' => 3]);
        $voucher->refresh();
        $voucher->items()->create([
            'description' => 'Light fixtures',
            'amount' => '100.00',
            'line_type' => 'expense',
            'sort_order' => 0,
        ]);

        $this->actingAs($employee)
            ->get("/vouchers/{$voucher->id}/edit")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('Vouchers/Form')
                ->where('voucher.id', $voucher->id)
                ->where('voucher.record_version', 3)
                ->where('voucher.items.0.description', 'Light fixtures')
                ->has('projects', 1)
                ->where('projects.0.id', $project->id));
    }

    public function test_voucher_entry_can_add_a_project_and_receive_its_id(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)
            ->post('/vouchers/projects', ['name' => '  East   Wing '])
            ->assertRedirect('/vouchers/create')
            ->assertSessionHas('createdProject', [
                'id' => 1,
                'name' => 'East Wing',
            ]);

        $this->assertDatabaseHas('projects', [
            'name' => 'East Wing',
            'normalized_name' => 'east wing',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $employee->id,
            'action' => 'project.created',
        ]);
    }

    public function test_user_with_edit_permission_can_add_a_project_from_voucher_edit(): void
    {
        $employee = $this->employee();
        $voucher = Voucher::factory()->create();
        $createPermission = Permission::query()->where('name', 'vouchers.create')->firstOrFail();
        $employee->roles()->firstOrFail()->permissions()->detach($createPermission);

        $response = $this->actingAs($employee)
            ->post("/vouchers/{$voucher->id}/projects", ['name' => 'North Wing'])
            ->assertRedirect("/vouchers/{$voucher->id}/edit");
        $project = Project::query()->where('name', 'North Wing')->firstOrFail();

        $response->assertSessionHas('createdProject', [
            'id' => $project->id,
            'name' => 'North Wing',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $employee->id,
            'action' => 'project.created',
        ]);
    }

    public function test_user_without_create_permission_cannot_add_a_project_from_voucher_entry(): void
    {
        $employee = $this->employee();
        $createPermission = Permission::query()->where('name', 'vouchers.create')->firstOrFail();
        $employee->roles()->firstOrFail()->permissions()->detach($createPermission);

        $this->actingAs($employee)
            ->post('/vouchers/projects', ['name' => 'East Wing'])
            ->assertForbidden();

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_voucher_history_shows_revision_and_audit_actors(): void
    {
        $employee = $this->employee();
        $voucher = Voucher::factory()->create();
        $revision = VoucherRevision::factory()
            ->for($voucher)
            ->for($employee, 'actor')
            ->create([
                'revision_number' => 1,
                'snapshot' => ['payee' => $voucher->payee],
                'printed_at' => now(),
            ]);
        AuditEvent::create([
            'actor_id' => $employee->id,
            'action' => 'voucher.printed',
            'auditable_type' => Voucher::class,
            'auditable_id' => $voucher->id,
            'metadata' => ['after' => ['revision_number' => 1]],
        ]);

        $this->actingAs($employee)
            ->get("/vouchers/{$voucher->id}/history")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('Vouchers/History')
                ->where('voucher.id', $voucher->id)
                ->where('revisions.0.id', $revision->id)
                ->where('revisions.0.actor.name', $employee->name)
                ->where('events.data.0.action', 'voucher.printed')
                ->where('events.data.0.actor.name', $employee->name));
    }

    public function test_voucher_history_handles_missing_actor_and_metadata(): void
    {
        $employee = $this->employee();
        $voucher = Voucher::factory()->create();
        AuditEvent::create([
            'actor_id' => null,
            'action' => 'voucher.cancelled',
            'auditable_type' => Voucher::class,
            'auditable_id' => $voucher->id,
            'metadata' => null,
        ]);

        $this->actingAs($employee)
            ->get("/vouchers/{$voucher->id}/history")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('Vouchers/History')
                ->where('events.data.0.actor.name', 'Unavailable account')
                ->where('events.data.0.metadata', []));
    }

    private function employee(): User
    {
        $this->seed();
        $employee = User::factory()->create();
        $employee->roles()->attach(Role::query()->where('name', 'employee')->firstOrFail());

        return $employee;
    }
}
