<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $permissions = [
            'accounts.manage' => 'Manage accounts',
            'permissions.manage' => 'Manage permissions',
            'vouchers.view' => 'View vouchers',
            'vouchers.create' => 'Create vouchers',
            'vouchers.edit' => 'Edit vouchers',
            'vouchers.print' => 'Print vouchers',
            'vouchers.export' => 'Export vouchers',
            'vouchers.cancel' => 'Cancel vouchers',
            'projects.manage' => 'Manage projects',
            'settings.manage' => 'Manage settings',
        ];

        foreach ($permissions as $name => $label) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['label' => $label],
            );
        }

        $superAdmin = Role::firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'web'],
            ['label' => 'Super Admin'],
        );
        $employee = Role::firstOrCreate(
            ['name' => 'employee', 'guard_name' => 'web'],
            ['label' => 'Employee'],
        );
        $superAdmin->syncPermissions(Permission::all());
        $employee->syncPermissions(Permission::whereIn('name', [
            'vouchers.view',
            'vouchers.create',
            'vouchers.edit',
        ])->get());
    }
}
