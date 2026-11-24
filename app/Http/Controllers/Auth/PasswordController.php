<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $request->user()->update([
                'password' => Hash::make($validated['password']),
                'must_change_password' => false,
            ]);
            AuditEvent::create([
                'actor_id' => $request->user()->id,
                'action' => 'account.password_changed',
                'auditable_type' => User::class,
                'auditable_id' => $request->user()->id,
            ]);
        });

        return back();
    }
}
