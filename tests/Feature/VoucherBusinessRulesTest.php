<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VoucherBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_creates_a_voucher_using_entry_year_and_server_calculated_total(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 26)->setTime(10, 30));
        $employee = $this->employee();
        $project = $this->project();
        $otherUser = User::factory()->create();

        $this->actingAs($employee)
            ->post('/vouchers', array_merge($this->voucherPayload($project), [
                'voucher_number' => 'DV-1999-9999',
                'created_by' => $otherUser->id,
                'project_name_snapshot' => 'Forged Project',
                'total_amount' => '0.01',
                'status' => 'cancelled',
            ]))
            ->assertRedirect('/vouchers');

        $voucherId = DB::table('vouchers')
            ->where('voucher_number', 'DV-2026-0001')
            ->value('id');

        $this->assertNotNull($voucherId);
        $voucher = Voucher::query()->findOrFail($voucherId);
        $this->assertSame($voucherId, $project->vouchers()->sole()->id);
        $this->assertSame($voucherId, $employee->createdVouchers()->sole()->id);
        $this->assertSame('voucher.created', $voucher->auditEvents()->sole()->action);
        $this->assertDatabaseHas('vouchers', [
            'voucher_number' => 'DV-2026-0001',
            'voucher_date' => '2021-01-02',
            'payee' => 'Northwind Supplies',
            'project_id' => $project->id,
            'project_name_snapshot' => 'West Wing',
            'scope_of_work' => 'Replace lighting',
            'expense_category' => 'Repairs',
            'payment_type' => 'partial',
            'created_by' => $employee->id,
            'prepared_by' => 'Ada Employee',
            'total_amount' => '74.50',
            'status' => 'draft',
            'created_at' => '2026-11-26 10:30:00',
        ]);
        $this->assertDatabaseHas('voucher_items', [
            'voucher_id' => $voucherId,
            'description' => 'Light fixtures',
            'amount' => '100.00',
            'line_type' => 'expense',
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('voucher_items', [
            'voucher_id' => $voucherId,
            'description' => 'Returned parts',
            'amount' => '25.50',
            'line_type' => 'deduction',
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $employee->id,
            'action' => 'voucher.created',
            'auditable_type' => Voucher::class,
            'auditable_id' => $voucherId,
        ]);
    }

    public function test_voucher_number_sequence_grows_past_four_digits(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 26)->setTime(10, 30));
        $employee = $this->employee();
        $project = $this->project();

        DB::table('voucher_number_sequences')->insert([
            'year' => 2026,
            'last_number' => 9999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($employee)
            ->post('/vouchers', $this->voucherPayload($project))
            ->assertRedirect('/vouchers');

        $this->assertDatabaseHas('vouchers', [
            'voucher_number' => 'DV-2026-10000',
        ]);
    }

    public function test_employee_can_edit_a_draft_without_changing_creator_number_or_project_snapshot(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 26)->setTime(10, 30));
        $employee = $this->employee('Ada Employee');
        $project = $this->project();

        $this->actingAs($employee)
            ->post('/vouchers', $this->voucherPayload($project))
            ->assertRedirect('/vouchers');

        $voucherId = DB::table('vouchers')
            ->where('voucher_number', 'DV-2026-0001')
            ->value('id');
        $project->update(['name' => 'Renamed West Wing', 'normalized_name' => 'renamed west wing']);
        $this->travel(5)->seconds();

        $this->actingAs($employee)
            ->patch("/vouchers/{$voucherId}", array_merge(
                $this->voucherPayload($project, [
                    'payee' => 'Updated Supplies',
                    'prepared_by' => 'Riley Prepared',
                    'items' => [
                        ['description' => 'New fixtures', 'amount' => '60.00', 'line_type' => 'expense'],
                        ['description' => 'Supplier credit', 'amount' => '15.00', 'line_type' => 'deduction'],
                    ],
                ]),
                [
                    'record_version' => 1,
                    'voucher_number' => 'DV-2000-9999',
                    'created_by' => User::factory()->create()->id,
                    'project_name_snapshot' => 'Forged Project',
                    'total_amount' => '0.01',
                    'status' => 'cancelled',
                ],
            ))
            ->assertRedirect('/vouchers');

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucherId,
            'voucher_number' => 'DV-2026-0001',
            'payee' => 'Updated Supplies',
            'project_name_snapshot' => 'West Wing',
            'created_by' => $employee->id,
            'prepared_by' => 'Riley Prepared',
            'total_amount' => '45.00',
            'status' => 'draft',
            'created_at' => '2026-11-26 10:30:00',
            'updated_at' => '2026-11-26 10:30:05',
        ]);
        $this->assertSame(2, (int) Voucher::query()->findOrFail($voucherId)->record_version);
        $this->assertDatabaseHas('voucher_items', [
            'voucher_id' => $voucherId,
            'description' => 'New fixtures',
            'amount' => '60.00',
            'line_type' => 'expense',
            'sort_order' => 0,
        ]);
        $this->assertDatabaseMissing('voucher_items', [
            'voucher_id' => $voucherId,
            'description' => 'Light fixtures',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $employee->id,
            'action' => 'voucher.updated',
            'auditable_id' => $voucherId,
        ]);
    }

    public function test_new_vouchers_start_at_record_version_one(): void
    {
        $employee = $this->employee();
        $project = $this->project();

        $this->actingAs($employee)
            ->post('/vouchers', $this->voucherPayload($project))
            ->assertRedirect('/vouchers');

        $voucher = Voucher::query()->firstOrFail();

        $this->assertSame(1, (int) $voucher->record_version);
    }

    public function test_draft_edits_require_a_record_version(): void
    {
        $employee = $this->employee();
        $project = $this->project();

        $this->actingAs($employee)
            ->post('/vouchers', $this->voucherPayload($project))
            ->assertRedirect('/vouchers');

        $voucher = Voucher::query()->firstOrFail();

        $this->actingAs($employee)
            ->patch("/vouchers/{$voucher->id}", $this->voucherPayload($project))
            ->assertSessionHasErrors('record_version');
    }

    public function test_stale_draft_edit_returns_conflict_without_mutating_voucher_or_audit(): void
    {
        $employee = $this->employee();
        $project = $this->project();

        $this->actingAs($employee)
            ->post('/vouchers', $this->voucherPayload($project))
            ->assertRedirect('/vouchers');

        $voucher = Voucher::query()->firstOrFail();
        $firstEdit = $this->voucherPayload($project, [
            'payee' => 'First edit',
            'record_version' => 1,
        ]);

        $this->actingAs($employee)
            ->patch("/vouchers/{$voucher->id}", $firstEdit)
            ->assertRedirect('/vouchers');

        $auditCountAfterFirstEdit = DB::table('audit_events')->count();

        $this->actingAs($employee)
            ->patch("/vouchers/{$voucher->id}", $this->voucherPayload($project, [
                'payee' => 'Stale edit',
                'record_version' => 1,
            ]))
            ->assertConflict();

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'payee' => 'First edit',
        ]);
        $this->assertSame($auditCountAfterFirstEdit, DB::table('audit_events')->count());
    }

    public function test_voucher_creation_requires_header_fields_and_at_least_one_item(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)
            ->post('/vouchers', [])
            ->assertSessionHasErrors(['voucher_date', 'payee', 'project_id', 'payment_type', 'items']);

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertDatabaseCount('voucher_number_sequences', 0);
    }

    #[DataProvider('invalidVoucherAmounts')]
    public function test_voucher_items_reject_non_positive_or_non_decimal_amounts(string $amount): void
    {
        $employee = $this->employee();
        $project = $this->project();

        $this->actingAs($employee)
            ->post('/vouchers', $this->voucherPayload($project, [
                'items' => [
                    ['description' => 'Invalid amount', 'amount' => $amount, 'line_type' => 'expense'],
                ],
            ]))
            ->assertSessionHasErrors([
                'items.0.amount' => 'Each amount must be positive with no more than two decimal places.',
            ]);

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertDatabaseCount('voucher_number_sequences', 0);
    }

    public function test_voucher_items_reject_unknown_line_types(): void
    {
        $employee = $this->employee();
        $project = $this->project();

        $this->actingAs($employee)
            ->post('/vouchers', $this->voucherPayload($project, [
                'items' => [
                    ['description' => 'Unsupported line', 'amount' => '1.00', 'line_type' => 'refund'],
                ],
            ]))
            ->assertSessionHasErrors([
                'items.0.line_type' => 'Each line must be an expense or deduction.',
            ]);

        $this->assertDatabaseCount('vouchers', 0);
    }

    public static function invalidVoucherAmounts(): array
    {
        return [
            'zero' => ['0.00'],
            'negative' => ['-1.00'],
            'fractional cent' => ['1.001'],
            'exponent notation' => ['1e3'],
        ];
    }

    public function test_merged_projects_cannot_be_selected_for_new_vouchers(): void
    {
        $employee = $this->employee();
        $project = $this->project();
        $project->update(['merged_into_id' => $this->project('Main Building')->id]);

        $this->actingAs($employee)
            ->post('/vouchers', $this->voucherPayload($project))
            ->assertSessionHasErrors([
                'project_id' => 'Select an active project.',
            ]);

        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_users_without_voucher_create_permission_are_forbidden(): void
    {
        $employee = $this->employee();
        $project = $this->project();
        $createPermission = Permission::query()->where('name', 'vouchers.create')->firstOrFail();
        $employee->roles()->firstOrFail()->permissions()->detach($createPermission);

        $this->actingAs($employee)
            ->post('/vouchers', $this->voucherPayload($project))
            ->assertForbidden();

        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_unauthenticated_users_are_redirected_before_voucher_validation(): void
    {
        $this->post('/vouchers', [])
            ->assertRedirect('/login');

        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_users_without_voucher_edit_permission_cannot_edit_drafts(): void
    {
        $employee = $this->employee();
        $project = $this->project();
        $this->actingAs($employee)
            ->post('/vouchers', $this->voucherPayload($project))
            ->assertRedirect('/vouchers');
        $voucherId = DB::table('vouchers')->value('id');
        $editPermission = Permission::query()->where('name', 'vouchers.edit')->firstOrFail();
        $employee->roles()->firstOrFail()->permissions()->detach($editPermission);

        $this->actingAs($employee)
            ->patch("/vouchers/{$voucherId}", $this->voucherPayload($project, [
                'payee' => 'Unauthorized change',
            ]))
            ->assertForbidden();

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucherId,
            'payee' => 'Northwind Supplies',
        ]);
        $this->assertDatabaseCount('audit_events', 1);
    }

    private function employee(string $name = 'Ada Employee'): User
    {
        $this->seed();
        $employee = User::factory()->create(['name' => $name]);
        $employee->roles()->attach(Role::query()->where('name', 'employee')->firstOrFail());

        return $employee;
    }

    private function project(string $name = 'West Wing'): Project
    {
        return Project::create([
            'name' => $name,
            'normalized_name' => strtolower($name),
        ]);
    }

    private function voucherPayload(Project $project, array $overrides = []): array
    {
        return array_replace([
            'voucher_date' => '2021-01-02',
            'payee' => 'Northwind Supplies',
            'project_id' => $project->id,
            'scope_of_work' => 'Replace lighting',
            'expense_category' => 'Repairs',
            'payment_type' => 'partial',
            'items' => [
                ['description' => 'Light fixtures', 'amount' => '100.00', 'line_type' => 'expense'],
                ['description' => 'Returned parts', 'amount' => '25.50', 'line_type' => 'deduction'],
            ],
        ], $overrides);
    }
}
