<?php

namespace Tests\Feature;

use App\Events\ProjectChanged;
use App\Events\VoucherChanged;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BroadcastingTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            'broadcasting.connections.reverb.options.port' => 6001,
            'broadcasting.connections.reverb.options.scheme' => 'http',
            'broadcasting.connections.reverb.options.useTLS' => false,
        ]);

        Broadcast::forgetDrivers();

        require base_path('routes/channels.php');
    }

    public static function voucherChannelPermissions(): array
    {
        return [
            'view permission' => ['vouchers.view'],
            'edit permission' => ['vouchers.edit'],
        ];
    }

    public static function projectChannelPermissions(): array
    {
        return [
            'voucher view permission' => ['vouchers.view'],
            'voucher create permission' => ['vouchers.create'],
            'voucher edit permission' => ['vouchers.edit'],
            'project management permission' => ['projects.manage'],
        ];
    }

    #[DataProvider('voucherChannelPermissions')]
    public function test_voucher_channel_authorizes_users_with_view_or_edit_permission(string $permission): void
    {
        $user = $this->userWithPermissions([$permission]);

        $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-vouchers',
            ])
            ->assertOk();
    }

    #[DataProvider('projectChannelPermissions')]
    public function test_project_channel_authorizes_users_with_voucher_or_project_access(string $permission): void
    {
        $user = $this->userWithPermissions([$permission]);

        $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-projects',
            ])
            ->assertOk();
    }

    public function test_private_channels_forbid_users_without_access_permissions(): void
    {
        $user = $this->userWithPermissions([]);

        $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-vouchers',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-projects',
            ])
            ->assertForbidden();
    }

    public function test_private_channels_require_an_authenticated_user(): void
    {
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-vouchers',
        ])->assertForbidden();
    }

    public function test_creating_a_voucher_dispatches_its_committed_version(): void
    {
        $actor = $this->userWithPermissions(['vouchers.view', 'vouchers.create']);
        $project = Project::factory()->create();
        Event::fake([VoucherChanged::class]);

        $this->actingAs($actor)
            ->post('/vouchers', $this->voucherPayload($project))
            ->assertRedirect('/vouchers');

        $voucher = Voucher::query()->sole();

        Event::assertDispatched(
            VoucherChanged::class,
            fn (VoucherChanged $event): bool => $event->voucherId === $voucher->id
                && $event->recordVersion === 1
                && $event->changeType === 'created'
                && $event->broadcastWith() === [
                    'voucher_id' => $voucher->id,
                    'record_version' => 1,
                    'change_type' => 'created',
                ],
        );
    }

    public function test_updating_a_voucher_dispatches_its_new_record_version(): void
    {
        $actor = $this->userWithPermissions(['vouchers.view', 'vouchers.edit']);
        $project = Project::factory()->create();
        $voucher = Voucher::factory()->create([
            'project_id' => $project->id,
            'project_name_snapshot' => $project->name,
        ]);
        Event::fake([VoucherChanged::class]);

        $this->actingAs($actor)
            ->patch("/vouchers/{$voucher->id}", $this->voucherPayload($project, [
                'record_version' => 1,
                'payee' => 'Updated supplier',
            ]))
            ->assertRedirect('/vouchers');

        Event::assertDispatched(
            VoucherChanged::class,
            fn (VoucherChanged $event): bool => $event->voucherId === $voucher->id
                && $event->recordVersion === 2
                && $event->changeType === 'updated'
                && $event->broadcastWith() === [
                    'voucher_id' => $voucher->id,
                    'record_version' => 2,
                    'change_type' => 'updated',
                ],
        );
    }

    public function test_cancelling_a_voucher_dispatches_its_new_record_version(): void
    {
        $actor = $this->userWithPermissions(['vouchers.view', 'vouchers.cancel']);
        $voucher = Voucher::factory()->create();
        Event::fake([VoucherChanged::class]);

        $this->actingAs($actor)
            ->post("/vouchers/{$voucher->id}/cancel", [
                'record_version' => 1,
                'reason' => 'No longer required',
            ])
            ->assertRedirect('/vouchers');

        Event::assertDispatched(
            VoucherChanged::class,
            fn (VoucherChanged $event): bool => $event->voucherId === $voucher->id
                && $event->recordVersion === 2
                && $event->changeType === 'cancelled'
                && $event->broadcastWith() === [
                    'voucher_id' => $voucher->id,
                    'record_version' => 2,
                    'change_type' => 'cancelled',
                ],
        );
    }

    public function test_creating_a_project_dispatches_its_change(): void
    {
        $actor = $this->userWithPermissions(['vouchers.view', 'vouchers.create']);
        Event::fake([ProjectChanged::class]);

        $this->actingAs($actor)
            ->post('/projects', ['name' => 'East Wing'])
            ->assertRedirect('/projects');

        $project = Project::query()->sole();

        Event::assertDispatched(
            ProjectChanged::class,
            fn (ProjectChanged $event): bool => $event->projectId === $project->id
                && $event->changeType === 'created'
                && $event->broadcastWith() === [
                    'project_id' => $project->id,
                    'change_type' => 'created',
                ],
        );
    }

    public function test_matching_project_name_does_not_dispatch_a_create_change(): void
    {
        $actor = $this->userWithPermissions(['vouchers.view', 'vouchers.create']);
        $project = Project::factory()->create([
            'name' => 'West Wing',
            'normalized_name' => 'west wing',
        ]);
        Event::fake([ProjectChanged::class]);

        $this->actingAs($actor)
            ->post('/projects', ['name' => ' WEST   wing '])
            ->assertRedirect('/projects');

        $this->assertSame(1, Project::query()->count());
        Event::assertNotDispatched(ProjectChanged::class);
    }

    public function test_renaming_a_project_dispatches_its_change(): void
    {
        $actor = $this->userWithPermissions(['projects.manage']);
        $project = Project::factory()->create();
        Event::fake([ProjectChanged::class]);

        $this->actingAs($actor)
            ->patch("/admin/projects/{$project->id}", ['name' => 'Renamed project'])
            ->assertRedirect('/projects');

        Event::assertDispatched(
            ProjectChanged::class,
            fn (ProjectChanged $event): bool => $event->projectId === $project->id
                && $event->changeType === 'renamed'
                && $event->broadcastWith() === [
                    'project_id' => $project->id,
                    'change_type' => 'renamed',
                ],
        );
    }

    public function test_merging_a_project_dispatches_its_change(): void
    {
        $actor = $this->userWithPermissions(['projects.manage']);
        $source = Project::factory()->create();
        $target = Project::factory()->create();
        Event::fake([ProjectChanged::class]);

        $this->actingAs($actor)
            ->post("/admin/projects/{$source->id}/merge", ['target_project_id' => $target->id])
            ->assertRedirect('/projects');

        Event::assertDispatched(
            ProjectChanged::class,
            fn (ProjectChanged $event): bool => $event->projectId === $source->id
                && $event->changeType === 'merged'
                && $event->broadcastWith() === [
                    'project_id' => $source->id,
                    'change_type' => 'merged',
                ],
        );
    }

    public function test_rolling_back_a_voucher_change_does_not_broadcast(): void
    {
        $actor = $this->userWithPermissions(['vouchers.view', 'vouchers.create']);
        $project = Project::factory()->create();
        Event::fake([VoucherChanged::class]);

        try {
            DB::transaction(function () use ($actor, $project): void {
                $this->actingAs($actor)
                    ->post('/vouchers', $this->voucherPayload($project))
                    ->assertRedirect('/vouchers');

                throw new \RuntimeException('Rollback voucher creation.');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Rollback voucher creation.', $exception->getMessage());
        }

        $this->assertSame(0, Voucher::query()->count());
        Event::assertNotDispatched(VoucherChanged::class);
    }

    public function test_rolling_back_a_project_change_does_not_broadcast(): void
    {
        $actor = $this->userWithPermissions(['vouchers.view', 'vouchers.create']);
        Event::fake([ProjectChanged::class]);

        try {
            DB::transaction(function () use ($actor): void {
                $this->actingAs($actor)
                    ->post('/projects', ['name' => 'Rollback project'])
                    ->assertRedirect('/projects');

                throw new \RuntimeException('Rollback project creation.');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Rollback project creation.', $exception->getMessage());
        }

        $this->assertSame(0, Project::query()->count());
        Event::assertNotDispatched(ProjectChanged::class);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $this->seed();
        $user = User::factory()->create();
        $role = Role::query()->where('name', 'employee')->firstOrFail();
        $permissionIds = Permission::query()
            ->whereIn('name', $permissions)
            ->pluck('id');
        $role->permissions()->sync($permissionIds);
        $user->roles()->attach($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
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
