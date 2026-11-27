<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->string('guard_name')->default('web')->after('name');
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->string('guard_name')->default('web')->after('name');
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        $roleAssignments = DB::table('role_user')
            ->get(['role_id', 'user_id'])
            ->map(fn (object $assignment): array => [
                'role_id' => $assignment->role_id,
                'model_type' => User::class,
                'model_id' => $assignment->user_id,
            ])
            ->all();

        if ($roleAssignments !== []) {
            DB::table('model_has_roles')->insertOrIgnore($roleAssignments);
        }

        $rolePermissions = DB::table('permission_role')
            ->get(['permission_id', 'role_id'])
            ->map(fn (object $assignment): array => [
                'permission_id' => $assignment->permission_id,
                'role_id' => $assignment->role_id,
            ])
            ->all();

        if ($rolePermissions !== []) {
            DB::table('role_has_permissions')->insertOrIgnore($rolePermissions);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('model_has_permissions')->exists()) {
            throw new RuntimeException('Cannot roll back Spatie permissions while direct model permissions exist.');
        }

        $roleAssignments = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->get(['role_id', 'model_id'])
            ->map(fn (object $assignment): array => [
                'role_id' => $assignment->role_id,
                'user_id' => $assignment->model_id,
            ])
            ->all();

        if ($roleAssignments !== []) {
            DB::table('role_user')->insertOrIgnore($roleAssignments);
        }

        $rolePermissions = DB::table('role_has_permissions')
            ->get(['permission_id', 'role_id'])
            ->map(fn (object $assignment): array => [
                'permission_id' => $assignment->permission_id,
                'role_id' => $assignment->role_id,
            ])
            ->all();

        if ($rolePermissions !== []) {
            DB::table('permission_role')->insertOrIgnore($rolePermissions);
        }

        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');

        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropColumn('guard_name');
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('guard_name');
        });
    }
};
