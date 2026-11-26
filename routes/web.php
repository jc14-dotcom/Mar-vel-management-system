<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ProjectController as AdminProjectController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\VoucherController;
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

Route::middleware(['auth', 'password.changed'])
    ->get('/projects', [ProjectController::class, 'index'])
    ->name('projects.index');

Route::middleware(['auth', 'password.changed'])
    ->post('/projects', [ProjectController::class, 'store'])
    ->name('projects.store');

Route::middleware(['auth', 'password.changed', 'permission:vouchers.create'])
    ->post('/vouchers/projects', [ProjectController::class, 'storeForVoucher'])
    ->name('vouchers.projects.store');

Route::middleware(['auth', 'password.changed', 'permission:vouchers.edit'])
    ->post('/vouchers/{voucher}/projects', [ProjectController::class, 'storeForVoucher'])
    ->name('vouchers.edit.projects.store');

Route::middleware(['auth', 'password.changed', 'permission:vouchers.view'])
    ->get('/vouchers', [VoucherController::class, 'index'])
    ->name('vouchers.index');

Route::middleware(['auth', 'password.changed', 'permission:vouchers.create'])
    ->get('/vouchers/create', [VoucherController::class, 'create'])
    ->name('vouchers.create');

Route::middleware(['auth', 'password.changed', 'permission:vouchers.create'])
    ->post('/vouchers', [VoucherController::class, 'store'])
    ->name('vouchers.store');

Route::middleware(['auth', 'password.changed', 'permission:vouchers.edit'])
    ->get('/vouchers/{voucher}/edit', [VoucherController::class, 'edit'])
    ->name('vouchers.edit');

Route::middleware(['auth', 'password.changed', 'permission:vouchers.edit'])
    ->patch('/vouchers/{voucher}', [VoucherController::class, 'update'])
    ->name('vouchers.update');

Route::middleware(['auth', 'password.changed', 'permission:vouchers.view'])
    ->get('/vouchers/{voucher}/history', [VoucherController::class, 'history'])
    ->name('vouchers.history');

Route::middleware(['auth', 'password.changed', 'permission:vouchers.cancel'])
    ->post('/vouchers/{voucher}/cancel', [VoucherController::class, 'cancel'])
    ->name('vouchers.cancel');

Route::middleware(['auth', 'password.changed', 'permission:projects.manage'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::patch('projects/{project}', [AdminProjectController::class, 'update'])->name('projects.update');
        Route::post('projects/{project}/merge', [AdminProjectController::class, 'merge'])->name('projects.merge');
    });

Route::middleware(['auth', 'password.changed', 'permission:settings.manage'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
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
