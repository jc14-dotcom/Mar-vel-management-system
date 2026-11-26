<?php

namespace Tests\Feature;

use App\Actions\Vouchers\RecordVoucherPrintAction;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class VoucherLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_print_creates_revision_snapshot_and_audit_event(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 26)->setTime(10, 30));
        $actor = User::factory()->create(['name' => 'Ada Employee']);
        $project = Project::factory()->create(['name' => 'West Wing']);
        $voucher = Voucher::factory()->create([
            'voucher_number' => 'DV-2026-0001',
            'voucher_date' => '2026-11-25',
            'payee' => 'Northwind Supplies',
            'project_id' => $project->id,
            'project_name_snapshot' => 'West Wing',
            'scope_of_work' => 'Replace lighting',
            'expense_category' => 'Repairs',
            'payment_type' => 'partial',
            'created_by' => $actor->id,
            'prepared_by' => 'Ada Employee',
            'total_amount' => '74.50',
            'status' => 'draft',
        ]);
        $voucher->items()->createMany([
            ['description' => 'Returned parts', 'amount' => '25.50', 'line_type' => 'deduction', 'sort_order' => 1],
            ['description' => 'Light fixtures', 'amount' => '100.00', 'line_type' => 'expense', 'sort_order' => 0],
        ]);

        $revision = app(RecordVoucherPrintAction::class)->handle($actor, $voucher);

        $this->assertSame(1, $revision->revision_number);
        $this->assertSame([
            'voucher_number' => 'DV-2026-0001',
            'voucher_date' => '2026-11-25',
            'payee' => 'Northwind Supplies',
            'project_id' => $project->id,
            'project_name_snapshot' => 'West Wing',
            'scope_of_work' => 'Replace lighting',
            'expense_category' => 'Repairs',
            'payment_type' => 'partial',
            'created_by' => $actor->id,
            'prepared_by' => 'Ada Employee',
            'total_amount' => '74.50',
            'items' => [
                ['description' => 'Light fixtures', 'amount' => '100.00', 'line_type' => 'expense', 'sort_order' => 0],
                ['description' => 'Returned parts', 'amount' => '25.50', 'line_type' => 'deduction', 'sort_order' => 1],
            ],
        ], $revision->snapshot);
        $this->assertNotNull($revision->printed_at);
        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'status' => 'printed',
            'record_version' => 2,
        ]);

        $auditEvent = $voucher->auditEvents()->latest('id')->firstOrFail();
        $this->assertSame($actor->id, $auditEvent->actor_id);
        $this->assertSame('voucher.printed', $auditEvent->action);
        $this->assertSame(1, $auditEvent->metadata['after']['revision_number']);
    }

    public function test_reprint_marks_pending_revision_printed_and_supersedes_previous(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 26)->setTime(10, 30));
        $actor = User::factory()->create();
        $voucher = Voucher::factory()->create([
            'status' => 'reprint_required',
            'record_version' => 2,
        ]);
        $previousRevision = VoucherRevision::factory()
            ->for($voucher)
            ->for($actor, 'actor')
            ->create([
                'revision_number' => 1,
                'snapshot' => ['payee' => 'Original payee'],
                'printed_at' => now()->subMinute(),
                'superseded_at' => null,
            ]);
        $pendingRevision = VoucherRevision::factory()
            ->for($voucher)
            ->for($actor, 'actor')
            ->create([
                'revision_number' => 2,
                'snapshot' => ['payee' => 'Updated payee'],
                'reason' => 'Corrected payee',
                'printed_at' => null,
                'superseded_at' => null,
            ]);

        try {
            $printedRevision = app(RecordVoucherPrintAction::class)->handle($actor, $voucher);
        } catch (HttpException) {
            $printedRevision = null;
        }

        $this->assertInstanceOf(VoucherRevision::class, $printedRevision);
        $this->assertSame($pendingRevision->id, $printedRevision->id);
        $this->assertSame(2, $printedRevision->revision_number);
        $this->assertSame(['payee' => 'Updated payee'], $printedRevision->snapshot);
        $this->assertNotNull($printedRevision->printed_at);
        $this->assertNotNull($previousRevision->fresh()->superseded_at);
        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'status' => 'printed',
            'record_version' => 3,
        ]);

        $auditEvent = $voucher->auditEvents()->latest('id')->firstOrFail();
        $this->assertSame($actor->id, $auditEvent->actor_id);
        $this->assertSame('voucher.printed', $auditEvent->action);
        $this->assertSame(2, $auditEvent->metadata['after']['revision_number']);
    }

    #[DataProvider('invalidPostPrintReasons')]
    public function test_post_print_edit_requires_a_non_blank_reason(string $status, ?string $reason): void
    {
        $actor = $this->employee();
        ['project' => $project, 'voucher' => $voucher] = $this->createPrintedVoucher($actor, $status);
        $payload = $this->editPayload($project, $voucher->record_version);

        if ($reason !== null) {
            $payload['reason'] = $reason;
        }

        $this->actingAs($actor)
            ->patch("/vouchers/{$voucher->id}", $payload)
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'status' => $status,
            'record_version' => $voucher->record_version,
            'payee' => $voucher->payee,
        ]);
        $this->assertDatabaseCount('voucher_revisions', 1);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public static function invalidPostPrintReasons(): array
    {
        return [
            'printed missing reason' => ['printed', null],
            'printed blank reason' => ['printed', '   '],
            'reprint-required missing reason' => ['reprint_required', null],
            'reprint-required blank reason' => ['reprint_required', '   '],
        ];
    }

    public function test_printed_voucher_edit_creates_pending_revision_without_superseding_current_print(): void
    {
        $actor = $this->employee();
        ['project' => $project, 'voucher' => $voucher, 'revision' => $previousRevision] = $this->createPrintedVoucher($actor);

        $this->actingAs($actor)
            ->patch("/vouchers/{$voucher->id}", $this->editPayload($project, $voucher->record_version, [
                'reason' => 'Corrected payee',
                'payee' => 'Updated Supplies',
                'items' => [
                    ['description' => 'Replacement parts', 'amount' => '60.00', 'line_type' => 'expense'],
                ],
            ]))
            ->assertRedirect('/vouchers');

        $pendingRevision = $voucher->revisions()->whereNull('printed_at')->sole();

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'status' => 'reprint_required',
            'record_version' => 3,
            'payee' => 'Updated Supplies',
        ]);
        $this->assertSame(2, $pendingRevision->revision_number);
        $this->assertSame('Corrected payee', $pendingRevision->reason);
        $this->assertSame('Updated Supplies', $pendingRevision->snapshot['payee']);
        $this->assertSame([
            ['description' => 'Replacement parts', 'amount' => '60.00', 'line_type' => 'expense', 'sort_order' => 0],
        ], $pendingRevision->snapshot['items']);
        $this->assertSame(['payee' => 'Northwind Supplies'], $previousRevision->fresh()->snapshot);
        $this->assertNull($previousRevision->fresh()->superseded_at);

        $auditEvent = $voucher->auditEvents()->latest('id')->firstOrFail();
        $this->assertSame('voucher.updated', $auditEvent->action);
        $this->assertSame('printed', $auditEvent->metadata['before']['status']);
        $this->assertSame('reprint_required', $auditEvent->metadata['after']['status']);
        $this->assertSame('Corrected payee', $auditEvent->metadata['reason']);
    }

    public function test_second_edit_before_reprint_updates_the_same_pending_revision(): void
    {
        $actor = $this->employee();
        ['project' => $project, 'voucher' => $voucher] = $this->createPrintedVoucher($actor);

        $this->actingAs($actor)
            ->patch("/vouchers/{$voucher->id}", $this->editPayload($project, 2, [
                'reason' => 'First correction',
            ]))
            ->assertRedirect('/vouchers');

        $pendingRevision = $voucher->revisions()->whereNull('printed_at')->sole();

        $this->actingAs($actor)
            ->patch("/vouchers/{$voucher->id}", $this->editPayload($project, 3, [
                'reason' => 'Second correction',
                'payee' => 'Final Supplies',
            ]))
            ->assertRedirect('/vouchers');

        $updatedPendingRevision = $pendingRevision->fresh();

        $this->assertSame($pendingRevision->id, $updatedPendingRevision->id);
        $this->assertSame(2, $updatedPendingRevision->revision_number);
        $this->assertSame('Second correction', $updatedPendingRevision->reason);
        $this->assertSame('Final Supplies', $updatedPendingRevision->snapshot['payee']);
        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'status' => 'reprint_required',
            'record_version' => 4,
            'payee' => 'Final Supplies',
        ]);
        $this->assertDatabaseCount('voucher_revisions', 2);
        $this->assertDatabaseCount('audit_events', 2);
    }

    #[DataProvider('invalidCancellationReasons')]
    public function test_cancellation_requires_a_non_blank_reason(?string $reason): void
    {
        $actor = $this->employeeWithCancelPermission();
        $voucher = Voucher::factory()->create();
        $payload = ['record_version' => 1];

        if ($reason !== null) {
            $payload['reason'] = $reason;
        }

        $this->actingAs($actor)
            ->post("/vouchers/{$voucher->id}/cancel", $payload)
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'status' => 'draft',
            'record_version' => 1,
        ]);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public static function invalidCancellationReasons(): array
    {
        return [
            'missing' => [null],
            'blank' => ['   '],
        ];
    }

    #[DataProvider('cancellableVoucherStatuses')]
    public function test_cancellation_accepts_each_active_lifecycle_status(string $status, int $recordVersion): void
    {
        $actor = $this->employeeWithCancelPermission();
        $voucher = Voucher::factory()->create([
            'status' => $status,
            'record_version' => $recordVersion,
        ]);

        $this->actingAs($actor)
            ->post("/vouchers/{$voucher->id}/cancel", [
                'record_version' => $recordVersion,
                'reason' => 'No longer required',
            ])
            ->assertRedirect('/vouchers');

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'status' => 'cancelled',
            'record_version' => $recordVersion + 1,
        ]);
    }

    public static function cancellableVoucherStatuses(): array
    {
        return [
            'draft' => ['draft', 1],
            'printed' => ['printed', 2],
            'reprint required' => ['reprint_required', 3],
        ];
    }

    public function test_stale_cancellation_returns_conflict_without_mutating_history(): void
    {
        $actor = $this->employeeWithCancelPermission();
        $voucher = Voucher::factory()->create([
            'status' => 'printed',
            'record_version' => 2,
        ]);
        $revision = VoucherRevision::factory()
            ->for($voucher)
            ->for($actor, 'actor')
            ->create([
                'revision_number' => 1,
                'snapshot' => ['payee' => $voucher->payee],
                'printed_at' => now()->subMinute(),
            ]);

        $this->actingAs($actor)
            ->post("/vouchers/{$voucher->id}/cancel", [
                'record_version' => 1,
                'reason' => 'No longer required',
            ])
            ->assertConflict();

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'status' => 'printed',
            'record_version' => 2,
        ]);
        $this->assertDatabaseHas('voucher_revisions', [
            'id' => $revision->id,
            'superseded_at' => null,
        ]);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_user_without_cancel_permission_is_forbidden(): void
    {
        $actor = $this->employee();
        $voucher = Voucher::factory()->create();
        $permission = Permission::query()->where('name', 'vouchers.cancel')->firstOrFail();
        $actor->roles()->firstOrFail()->permissions()->detach($permission);

        $this->actingAs($actor)
            ->post("/vouchers/{$voucher->id}/cancel", [
                'record_version' => 1,
                'reason' => 'No longer required',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'status' => 'draft',
            'record_version' => 1,
        ]);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_cancellation_retains_history_and_does_not_reuse_number(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 26)->setTime(10, 30));
        $actor = $this->employeeWithCancelPermission();
        $project = Project::factory()->create();
        $voucher = Voucher::factory()->create([
            'voucher_number' => 'DV-2026-0001',
            'project_id' => $project->id,
            'status' => 'printed',
            'record_version' => 2,
        ]);
        $voucher->items()->create([
            'description' => 'Original parts',
            'amount' => '100.00',
            'line_type' => 'expense',
            'sort_order' => 0,
        ]);
        $revision = VoucherRevision::factory()
            ->for($voucher)
            ->for($actor, 'actor')
            ->create([
                'revision_number' => 1,
                'snapshot' => ['payee' => $voucher->payee],
                'printed_at' => now()->subMinute(),
            ]);
        DB::table('voucher_number_sequences')->insert([
            'year' => 2026,
            'last_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($actor)
            ->post("/vouchers/{$voucher->id}/cancel", [
                'record_version' => 2,
                'reason' => 'Duplicate voucher',
            ])
            ->assertRedirect('/vouchers');

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'voucher_number' => 'DV-2026-0001',
            'status' => 'cancelled',
            'record_version' => 3,
        ]);
        $this->assertDatabaseHas('voucher_items', [
            'voucher_id' => $voucher->id,
            'description' => 'Original parts',
        ]);
        $this->assertDatabaseHas('voucher_revisions', [
            'id' => $revision->id,
            'voucher_id' => $voucher->id,
        ]);

        $auditEvent = $voucher->auditEvents()->latest('id')->firstOrFail();
        $this->assertSame($actor->id, $auditEvent->actor_id);
        $this->assertSame('voucher.cancelled', $auditEvent->action);
        $this->assertSame('Duplicate voucher', $auditEvent->metadata['reason']);
        $this->assertSame('printed', $auditEvent->metadata['before']['status']);
        $this->assertSame('cancelled', $auditEvent->metadata['after']['status']);

        $createPayload = $this->editPayload($project, 1);
        unset($createPayload['record_version']);

        $this->actingAs($actor)
            ->post('/vouchers', $createPayload)
            ->assertRedirect('/vouchers');

        $this->assertDatabaseHas('vouchers', ['voucher_number' => 'DV-2026-0002']);
    }

    public function test_cancelled_voucher_cannot_be_edited_or_printed(): void
    {
        $actor = $this->employeeWithCancelPermission();
        $project = Project::factory()->create();
        $voucher = Voucher::factory()->create([
            'project_id' => $project->id,
            'record_version' => 1,
        ]);

        $this->actingAs($actor)
            ->post("/vouchers/{$voucher->id}/cancel", [
                'record_version' => 1,
                'reason' => 'No longer required',
            ])
            ->assertRedirect('/vouchers');

        $this->actingAs($actor)
            ->patch("/vouchers/{$voucher->id}", $this->editPayload($project, 2))
            ->assertConflict();

        try {
            app(RecordVoucherPrintAction::class)->handle($actor, $voucher);
            $this->fail('Cancelled voucher should not be printable.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('audit_events', 1);
    }

    /**
     * @return array{project: Project, voucher: Voucher, revision: VoucherRevision}
     */
    private function createPrintedVoucher(User $actor, string $status = 'printed'): array
    {
        $project = Project::factory()->create(['name' => 'West Wing']);
        $voucher = Voucher::factory()->create([
            'voucher_number' => 'DV-2026-0001',
            'voucher_date' => '2026-11-25',
            'payee' => 'Northwind Supplies',
            'project_id' => $project->id,
            'project_name_snapshot' => 'West Wing',
            'scope_of_work' => 'Replace lighting',
            'expense_category' => 'Repairs',
            'payment_type' => 'partial',
            'created_by' => $actor->id,
            'prepared_by' => $actor->name,
            'total_amount' => '100.00',
            'status' => $status,
            'record_version' => 2,
        ]);
        $voucher->items()->create([
            'description' => 'Original parts',
            'amount' => '100.00',
            'line_type' => 'expense',
            'sort_order' => 0,
        ]);
        $revision = VoucherRevision::factory()
            ->for($voucher)
            ->for($actor, 'actor')
            ->create([
                'revision_number' => 1,
                'snapshot' => ['payee' => 'Northwind Supplies'],
                'printed_at' => now()->subMinute(),
                'superseded_at' => null,
            ]);

        return compact('project', 'voucher', 'revision');
    }

    private function employee(): User
    {
        $this->seed();
        $employee = User::factory()->create(['name' => 'Ada Employee']);
        $employee->roles()->attach(Role::query()->where('name', 'employee')->firstOrFail());

        return $employee;
    }

    private function employeeWithCancelPermission(): User
    {
        $employee = $this->employee();
        $permission = Permission::query()->where('name', 'vouchers.cancel')->firstOrFail();
        $employee->roles()->firstOrFail()->permissions()->syncWithoutDetaching([$permission->id]);

        return $employee;
    }

    private function editPayload(Project $project, int $recordVersion, array $overrides = []): array
    {
        return array_replace([
            'record_version' => $recordVersion,
            'voucher_date' => '2026-11-25',
            'payee' => 'Northwind Supplies',
            'project_id' => $project->id,
            'scope_of_work' => 'Replace lighting',
            'expense_category' => 'Repairs',
            'payment_type' => 'partial',
            'prepared_by' => 'Ada Employee',
            'items' => [
                ['description' => 'Original parts', 'amount' => '100.00', 'line_type' => 'expense'],
            ],
        ], $overrides);
    }
}
