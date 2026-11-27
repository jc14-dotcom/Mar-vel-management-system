<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Permissions', [
            'roles' => Role::query()
                ->with('permissions:id,name,label')
                ->orderBy('name')
                ->get(['id', 'name', 'label']),
            'permissions' => Permission::query()->orderBy('name')->get(['id', 'name', 'label']),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);
        $permissionIds = collect($validated['permissions'] ?? [])->unique()->values();
        $permissionsManageId = Permission::where('name', 'permissions.manage')->value('id');

        abort_if(
            $role->name === 'super_admin' && ! $permissionIds->contains($permissionsManageId),
            422,
            'Super Admins must retain permission administration access.',
        );

        DB::transaction(function () use ($request, $role, $permissionIds): void {
            $before = $role->permissions()->orderBy('name')->pluck('name')->all();
            $role->syncPermissions($permissionIds->all());
            $after = $role->permissions()->orderBy('name')->pluck('name')->all();

            AuditEvent::create([
                'actor_id' => $request->user()->id,
                'action' => 'role.permissions_updated',
                'auditable_type' => Role::class,
                'auditable_id' => $role->id,
                'metadata' => ['before' => $before, 'after' => $after],
            ]);
        });

        return redirect()->route('admin.permissions.index')->with('success', "{$role->label} permissions were updated.");
    }
}
