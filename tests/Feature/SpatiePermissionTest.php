<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpatiePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_are_inherited_from_a_spatie_role(): void
    {
        $this->seed();
        $user = User::factory()->create();

        $user->assignRole('employee');

        $this->assertTrue($user->fresh()->hasPermission('vouchers.view'));
        $this->assertFalse($user->fresh()->hasPermission('accounts.manage'));
    }
}
