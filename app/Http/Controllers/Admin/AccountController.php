<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountRequest;
use App\Models\AuditEvent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('Admin/Accounts', [
            'accounts' => User::query()->with('roles:id,name,label')->orderBy('name')->get([
                'id', 'name', 'email', 'is_active', 'created_at',
            ]),
            'roles' => Role::query()->orderBy('name')->get(['name', 'label']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create([
                'name' => $request->string('name')->trim()->value(),
                'email' => $request->string('email')->lower()->trim()->value(),
                'password' => Hash::make($request->string('password')->value()),
                'is_active' => true,
                'must_change_password' => true,
                'email_verified_at' => now(),
            ]);
            $user->roles()->sync([
                Role::where('name', $request->string('role')->value())->value('id'),
            ]);
            AuditEvent::create([
                'actor_id' => $request->user()->id,
                'action' => 'account.created',
                'auditable_type' => User::class,
                'auditable_id' => $user->id,
                'metadata' => ['role' => $request->string('role')->value()],
            ]);

            return $user;
        });

        return redirect()->route('admin.accounts.index')
            ->with('success', "{$user->name} was added.");
    }

    /**
     * Display the specified resource.
     */
    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        abort_if($request->user()->is($user), 422, 'You cannot disable your own account.');
        $wasActive = $user->is_active;
        DB::transaction(function () use ($request, $user, $validated, $wasActive): void {
            $user->update($validated);
            AuditEvent::create([
                'actor_id' => $request->user()->id,
                'action' => 'account.status_updated',
                'auditable_type' => User::class,
                'auditable_id' => $user->id,
                'metadata' => [
                    'before' => ['is_active' => $wasActive],
                    'after' => ['is_active' => $user->is_active],
                ],
            ]);
        });

        return back()->with('success', "{$user->name}'s access was updated.");
    }
}
