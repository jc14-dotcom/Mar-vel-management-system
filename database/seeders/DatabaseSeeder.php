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
            Permission::firstOrCreate(['name' => $name], ['label' => $label]);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin'], ['label' => 'Super Admin']);
        $employee = Role::firstOrCreate(['name' => 'employee'], ['label' => 'Employee']);
        $superAdmin->permissions()->sync(Permission::all());
        $employee->permissions()->sync(Permission::whereIn('name', [
            'vouchers.view',
            'vouchers.create',
            'vouchers.edit',
        ])->get());
    }
}
