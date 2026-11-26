<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $user?->loadMissing('roles:id,name,label');

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'can' => $user ? [
                    'manageAccounts' => $user->hasPermission('accounts.manage'),
                    'managePermissions' => $user->hasPermission('permissions.manage'),
                    'manageProjects' => $user->hasPermission('projects.manage'),
                    'manageSettings' => $user->hasPermission('settings.manage'),
                    'viewVouchers' => $user->hasPermission('vouchers.view'),
                    'createVouchers' => $user->hasPermission('vouchers.create'),
                    'editVouchers' => $user->hasPermission('vouchers.edit'),
                    'cancelVouchers' => $user->hasPermission('vouchers.cancel'),
                ] : [],
            ],
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
                'warning' => fn (): ?string => $request->session()->get('warning'),
                'createdProject' => fn (): ?array => $request->session()->get('createdProject'),
            ],
        ];
    }
}
