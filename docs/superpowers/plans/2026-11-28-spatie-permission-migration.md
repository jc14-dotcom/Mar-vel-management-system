# Spatie Permission Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the custom role/permission relationship layer with `spatie/laravel-permission` while preserving existing users, role labels, permission names, account creation, audit events, and server-side authorization.

**Architecture:** Keep Laravel user creation and `AuditEvent` as application-owned behavior. Add Spatie as the authorization implementation, using the existing `roles` and `permissions` tables with additive `guard_name` columns and new Spatie pivot tables. Copy current role assignments and role permissions into the new pivots; leave legacy pivots intact for rollback/reference until a separate cleanup decision is approved.

**Tech Stack:** Laravel 13, PHP 8.5, MySQL, Inertia, Vue 3, `spatie/laravel-permission`.

**Spec:** Approved user request to use Spatie Laravel Permission for the existing account/RBAC system.

## Global Constraints

- Preserve the existing permission names and `super_admin` / `employee` role names.
- Keep authorization in Laravel and keep the client-side `auth.can` props as presentation-only.
- Keep individual app-managed accounts, temporary-password enforcement, and audit events.
- Use migrations for schema changes; do not rewrite the applied custom RBAC migration.
- Do not add invitations, public registration, OAuth, or a separate API in this change.

## Review Focus

- Existing role and permission data must be copied without losing labels or assignments.
- Existing account and permission administration screens must keep their response shapes.
- Inactive users must remain unable to authorize even when they retain roles.
- All protected routes and private channels must continue using server-side permission checks.
- Tests and factories must use the new Spatie pivots rather than the legacy custom pivots.

### Task 1: Install and configure Spatie Permission

**Files:**
- Modify: `composer.json`, `composer.lock`
- Create/modify: `config/permission.php`
- Modify: `app/Models/User.php`, `app/Models/Role.php`, `app/Models/Permission.php`

- [ ] Add the compatible `spatie/laravel-permission` dependency.
- [ ] Publish and configure the package to use the application Role and Permission models and the `web` guard.
- [ ] Add `HasRoles` to User, remove the custom roles relation and duplicate role helper, and keep `hasPermission()` as an active-account wrapper around Spatie authorization.
- [ ] Make application Role and Permission extend Spatie models while retaining the existing `label` field used by the UI.

### Task 2: Add a forward-compatible data migration

**Files:**
- Create: `database/migrations/*_migrate_custom_rbac_to_spatie_permission.php`

- [ ] Add `guard_name` to existing roles and permissions with `web` defaults.
- [ ] Create Spatie’s `model_has_permissions`, `model_has_roles`, and `role_has_permissions` tables with integer model IDs.
- [ ] Copy existing `role_user` rows with `App\\Models\\User` model types and existing `permission_role` rows into the new pivots.
- [ ] Keep the legacy pivot tables untouched for rollback/reference; do not hard-delete existing authorization history.
- [ ] Make the migration idempotent within a fresh migration run and provide a reversible down path for only the newly created columns/tables.

### Task 3: Update account and permission workflows

**Files:**
- Modify: `app/Http/Controllers/Admin/AccountController.php`
- Modify: `app/Http/Controllers/Admin/PermissionController.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `app/Console/Commands/BootstrapAdmin.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`

- [ ] Use Spatie role assignment and permission synchronization APIs while preserving current validation, labels, audit metadata, and transaction boundaries.
- [ ] Keep the existing `accounts.manage` and `permissions.manage` protections.
- [ ] Ensure shared Inertia props still expose account roles and the existing boolean `auth.can` map.

### Task 4: Update and verify authorization coverage

**Files:**
- Modify: affected feature tests under `tests/Feature/`
- Modify: factories/helpers only where required by Spatie relationships.

- [ ] Update test setup to assign roles and permissions through Spatie’s relationships.
- [ ] Add focused assertions for migrated roles, permission inheritance, inactive-user denial, account creation role assignment, and permission-set auditing.
- [ ] Run the narrowest RBAC tests, frontend build if response contracts change, Pint for PHP changes, and the full available test suite.

## Verification

- `composer show spatie/laravel-permission`
- `php artisan migrate --pretend --no-interaction`
- `php artisan test --compact tests/Feature/StageOneAccessTest.php`
- `vendor/bin/pint --dirty --format agent`
- `git diff --check`

If PHPUnit remains blocked by the existing missing SQLite driver, report that limitation with the exact error and use the configured MySQL schema checks plus static review for the migration.
