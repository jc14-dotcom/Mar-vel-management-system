<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => false,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'password.changed'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'password.changed', 'permission:accounts.manage'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
        Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
        Route::patch('accounts/{user}/status', [AccountController::class, 'updateStatus'])->name('accounts.status');
    });

Route::middleware(['auth', 'password.changed', 'permission:permissions.manage'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::patch('permissions/roles/{role}', [PermissionController::class, 'update'])->name('permissions.roles.update');
    });

require __DIR__.'/auth.php';
